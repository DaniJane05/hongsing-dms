<?php

require_once __DIR__ . '/../config/constants.php';

/**
 * Send one eligible delivery combination to the Python ML API.
 *
 * IMPORTANT:
 * driver_id and truck_id are metadata only.
 * They identify the candidate but are NOT predictive ML features.
 */
function predictDeliveryTAT(array $data): array
{
    $url = rtrim(ML_SERVICE_URL, '/') . '/predict';

    $payload = [
        // Candidate metadata
        'driver_id' => intval($data['driver_id'] ?? 0),
        'truck_id' => intval($data['truck_id'] ?? 0),

        // Order / route features
        'weight_kg' => floatval($data['weight_kg'] ?? 0),

        'priority_level' => (
            isset($data['priority_level'])
            && $data['priority_level'] === 'Urgent'
        ) ? 'Urgent' : 'Normal',

        'route_distance_km' => floatval(
            $data['route_distance_km'] ?? 0
        ),

        'estimated_route_time_minutes' => intval(
            $data['estimated_route_time_minutes'] ?? 0
        ),

        // Driver features
        'driver_rating' => floatval(
            $data['driver_rating'] ?? 4.2
        ),

        'driver_total_deliveries' => intval(
            $data['driver_total_deliveries'] ?? 0
        ),

        'driver_delay_rate' => floatval(
            $data['driver_delay_rate'] ?? 0
        ),

        'driver_verified_violation_rate' => floatval(
            $data['driver_verified_violation_rate'] ?? 0
        ),

        // Truck features
        'truck_capacity_kg' => floatval(
            $data['truck_capacity_kg'] ?? 0
        ),

        'truck_current_load_kg' => floatval(
            $data['truck_current_load_kg'] ?? 0
        ),

        'truck_breakdown_rate' => floatval(
            $data['truck_breakdown_rate'] ?? 0
        ),
    ];

    $jsonPayload = json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
    );

    if ($jsonPayload === false) {
        return [
            'success' => false,
            'error' => 'Unable to encode ML request.'
        ];
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonPayload,

        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],

        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);

    $httpCode = intval(
        curl_getinfo($ch, CURLINFO_HTTP_CODE)
    );

    curl_close($ch);

    if ($response === false) {
        return [
            'success' => false,
            'error' => (
                'Unable to connect to ML service. '
                . ($curlError ?: 'Unknown cURL error.')
            )
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {

        $errorResponse = json_decode(
            $response,
            true
        );

        return [
            'success' => false,
            'error' => (
                $errorResponse['error']
                ?? 'ML service returned HTTP ' . $httpCode
            ),
            'http_code' => $httpCode
        ];
    }

    $result = json_decode(
        $response,
        true
    );

    if (!is_array($result)) {
        return [
            'success' => false,
            'error' => 'Invalid JSON response from ML service.'
        ];
    }

    if (
        !isset($result['success'])
        || $result['success'] !== true
    ) {
        return [
            'success' => false,
            'error' => (
                $result['error']
                ?? 'ML prediction failed.'
            )
        ];
    }

    if (!isset($result['prediction'])) {
        return [
            'success' => false,
            'error' => (
                'ML response does not contain prediction data.'
            )
        ];
    }

    $prediction = $result['prediction'];

    $predictedTat = intval(
        $prediction['predicted_tat_minutes'] ?? 0
    );

    $minimumTat = intval(
        $prediction['estimated_tat_range']['minimum_minutes']
        ?? 0
    );

    $maximumTat = intval(
        $prediction['estimated_tat_range']['maximum_minutes']
        ?? 0
    );

    return [
        'success' => true,

        'predicted_tat_minutes' => $predictedTat,
        'minimum_tat_minutes' => $minimumTat,
        'maximum_tat_minutes' => $maximumTat,

        'input' => (
            $result['input']
            ?? $payload
        ),

        'model' => (
            $result['model']
            ?? []
        )
    ];
}


/**
 * Predict TAT for multiple RULE-ELIGIBLE driver/truck combinations.
 *
 * Eligibility must already be checked by PHP/business rules.
 * ML only predicts and ranks the valid candidates.
 */
function predictDeliveryCombinations(
    array $combinations
): array {

    $results = [];

    foreach ($combinations as $combination) {

        $prediction = predictDeliveryTAT(
            $combination
        );

        if (
            isset($prediction['success'])
            && $prediction['success'] === true
        ) {

            $results[] = array_merge(
                $combination,
                [
                    'predicted_tat_minutes' =>
                        $prediction['predicted_tat_minutes'],

                    'minimum_tat_minutes' =>
                        $prediction['minimum_tat_minutes'],

                    'maximum_tat_minutes' =>
                        $prediction['maximum_tat_minutes'],

                    'ml_model' =>
                        $prediction['model'] ?? [],

                    'prediction_error' => null
                ]
            );

        } else {

            $results[] = array_merge(
                $combination,
                [
                    'predicted_tat_minutes' => null,
                    'minimum_tat_minutes' => null,
                    'maximum_tat_minutes' => null,
                    'ml_model' => [],
                    'prediction_error' =>
                        (
                            $prediction['error']
                            ?? 'Unknown ML error.'
                        )
                ]
            );
        }
    }

    /*
     * Ranking:
     * 1. Successful predictions first.
     * 2. Lowest predicted TAT first.
     * 3. Lower driver delay rate.
     * 4. Lower verified violation rate.
     * 5. Lower truck breakdown rate.
     *
     * The secondary comparisons only break equal-TAT ties.
     */
    usort(
        $results,
        function ($a, $b) {

            $aTat = $a['predicted_tat_minutes'] ?? null;
            $bTat = $b['predicted_tat_minutes'] ?? null;

            if ($aTat === null && $bTat === null) {
                return 0;
            }

            if ($aTat === null) {
                return 1;
            }

            if ($bTat === null) {
                return -1;
            }

            $tatComparison = $aTat <=> $bTat;

            if ($tatComparison !== 0) {
                return $tatComparison;
            }

            $delayComparison =
                floatval($a['driver_delay_rate'] ?? 0)
                <=>
                floatval($b['driver_delay_rate'] ?? 0);

            if ($delayComparison !== 0) {
                return $delayComparison;
            }

            $violationComparison =
                floatval(
                    $a['driver_verified_violation_rate'] ?? 0
                )
                <=>
                floatval(
                    $b['driver_verified_violation_rate'] ?? 0
                );

            if ($violationComparison !== 0) {
                return $violationComparison;
            }

            return
                floatval($a['truck_breakdown_rate'] ?? 0)
                <=>
                floatval($b['truck_breakdown_rate'] ?? 0);
        }
    );

    /*
     * Add ranking after sorting.
     */
    foreach ($results as $index => &$result) {
        $result['recommendation_rank'] = $index + 1;
        $result['is_ml_recommended'] = (
            $index === 0
            && $result['predicted_tat_minutes'] !== null
        );
    }

    unset($result);

    return $results;
}