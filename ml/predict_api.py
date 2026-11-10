import os
import sys

import joblib
import pandas as pd

from flask import Flask, jsonify, request


# ============================================================
# HONG SING DMS
# ML PREDICTION API
# Delivery TAT Prediction
#
# Model version:
# RF-TAT-v2-incidents
# ============================================================


BASE_DIR = os.path.dirname(
    os.path.abspath(__file__)
)

MODEL_FILE = os.path.join(
    BASE_DIR,
    "models",
    "delivery_tat_model.pkl"
)

METRICS_FILE = os.path.join(
    BASE_DIR,
    "models",
    "model_metrics.txt"
)


app = Flask(__name__)


# ============================================================
# MODEL FEATURES
# ============================================================
#
# driver_id and truck_id are deliberately excluded.
#
# They are identifiers, not measurements of delivery
# performance.
# ============================================================

FEATURE_COLUMNS = [
    "weight_kg",
    "priority_level",
    "route_distance_km",
    "estimated_route_time_minutes",

    "driver_rating",
    "driver_total_deliveries",
    "driver_delay_rate",
    "driver_verified_violation_rate",

    "truck_capacity_kg",
    "truck_current_load_kg",
    "truck_breakdown_rate",
]


MODEL_VERSION = "RF-TAT-v2-incidents"


# ============================================================
# LOAD MODEL
# ============================================================

if not os.path.exists(MODEL_FILE):

    print("=" * 60)
    print("ERROR: ML MODEL NOT FOUND")
    print("=" * 60)
    print()
    print("Expected model:")
    print(MODEL_FILE)
    print()
    print("Run train_model.py first.")

    sys.exit(1)


try:

    model = joblib.load(
        MODEL_FILE
    )

except Exception as error:

    print("=" * 60)
    print("ERROR: COULD NOT LOAD ML MODEL")
    print("=" * 60)
    print(error)

    sys.exit(1)


# ============================================================
# HELPERS
# ============================================================

def clean_number(value, default=0.0):

    try:

        if value is None:
            return default

        return float(value)

    except (TypeError, ValueError):
        return default


def clean_integer(value, default=0):

    try:

        if value is None:
            return default

        return int(float(value))

    except (TypeError, ValueError):
        return default


def clamp(value, minimum, maximum):

    return max(
        minimum,
        min(value, maximum)
    )


def read_model_mae():

    """
    Read MAE from model_metrics.txt.

    This is only used to create an operational display range.
    It is NOT a statistical confidence interval.
    """

    default_mae = 10.0

    if not os.path.exists(METRICS_FILE):
        return default_mae

    try:

        with open(
            METRICS_FILE,
            "r",
            encoding="utf-8"
        ) as file:

            for line in file:

                if line.startswith("MAE:"):

                    value = (
                        line
                        .replace("MAE:", "")
                        .replace("minutes", "")
                        .strip()
                    )

                    return max(
                        1.0,
                        float(value)
                    )

    except Exception:
        pass

    return default_mae


def validate_input(data):

    missing = []

    for column in FEATURE_COLUMNS:

        if column not in data:
            missing.append(column)

    if missing:

        return False, (
            "Missing required ML fields: "
            + ", ".join(missing)
        )

    return True, None


def prepare_prediction_data(data):

    priority = str(
        data.get(
            "priority_level",
            "Normal"
        )
    ).strip()

    if priority not in (
        "Normal",
        "Urgent"
    ):
        priority = "Normal"

    return {

        "weight_kg":
            clean_number(
                data.get("weight_kg")
            ),

        "priority_level":
            priority,

        "route_distance_km":
            clean_number(
                data.get("route_distance_km")
            ),

        "estimated_route_time_minutes":
            clean_integer(
                data.get(
                    "estimated_route_time_minutes"
                )
            ),

        "driver_rating":
            clean_number(
                data.get("driver_rating"),
                4.20
            ),

        "driver_total_deliveries":
            clean_integer(
                data.get(
                    "driver_total_deliveries"
                )
            ),

        "driver_delay_rate":
            clean_number(
                data.get(
                    "driver_delay_rate"
                )
            ),

        "driver_verified_violation_rate":
            clean_number(
                data.get(
                    "driver_verified_violation_rate"
                )
            ),

        "truck_capacity_kg":
            clean_number(
                data.get(
                    "truck_capacity_kg"
                )
            ),

        "truck_current_load_kg":
            clean_number(
                data.get(
                    "truck_current_load_kg"
                )
            ),

        "truck_breakdown_rate":
            clean_number(
                data.get(
                    "truck_breakdown_rate"
                )
            ),
    }


def validate_business_rules(data):

    weight = data["weight_kg"]

    capacity = data[
        "truck_capacity_kg"
    ]

    current_load = data[
        "truck_current_load_kg"
    ]

    distance = data[
        "route_distance_km"
    ]

    route_time = data[
        "estimated_route_time_minutes"
    ]

    rating = data[
        "driver_rating"
    ]

    delay_rate = data[
        "driver_delay_rate"
    ]

    violation_rate = data[
        "driver_verified_violation_rate"
    ]

    breakdown_rate = data[
        "truck_breakdown_rate"
    ]

    if weight <= 0:

        return False, (
            "weight_kg must be greater than 0."
        )

    if capacity <= 0:

        return False, (
            "truck_capacity_kg must be greater than 0."
        )

    if current_load < 0:

        return False, (
            "truck_current_load_kg cannot be negative."
        )

    available_capacity = (
        capacity - current_load
    )

    if available_capacity < weight:

        return False, (
            "Delivery weight exceeds "
            "the truck's available capacity."
        )

    if distance <= 0:

        return False, (
            "route_distance_km must be greater than 0."
        )

    if route_time <= 0:

        return False, (
            "estimated_route_time_minutes "
            "must be greater than 0."
        )

    if rating < 0 or rating > 5:

        return False, (
            "driver_rating must be between 0 and 5."
        )

    rate_fields = {
        "driver_delay_rate": delay_rate,
        "driver_verified_violation_rate":
            violation_rate,
        "truck_breakdown_rate":
            breakdown_rate,
    }

    for field, value in rate_fields.items():

        if value < 0 or value > 1:

            return False, (
                f"{field} must be between 0 and 1."
            )

    return True, None


# ============================================================
# ROUTES
# ============================================================

@app.route(
    "/",
    methods=["GET"]
)
def home():

    return jsonify(
        {
            "success": True,
            "service":
                "Hong Sing DMS ML Prediction API",

            "model":
                "Delivery TAT Prediction",

            "model_version":
                MODEL_VERSION,

            "status":
                "online"
        }
    )


@app.route(
    "/health",
    methods=["GET"]
)
def health():

    return jsonify(
        {
            "success": True,
            "status": "healthy",
            "model_loaded": True,
            "model_version": MODEL_VERSION
        }
    )


@app.route(
    "/predict",
    methods=["POST"]
)
def predict():

    try:

        data = request.get_json(
            silent=True
        )

        if not data:

            return jsonify(
                {
                    "success": False,
                    "error":
                        "Request body must contain JSON data."
                }
            ), 400

        valid, error = validate_input(
            data
        )

        if not valid:

            return jsonify(
                {
                    "success": False,
                    "error": error
                }
            ), 400

        prediction_data = (
            prepare_prediction_data(
                data
            )
        )

        valid, error = (
            validate_business_rules(
                prediction_data
            )
        )

        if not valid:

            return jsonify(
                {
                    "success": False,
                    "error": error
                }
            ), 400

        input_df = pd.DataFrame(
            [
                prediction_data
            ],
            columns=FEATURE_COLUMNS
        )

        predicted_tat = model.predict(
            input_df
        )[0]

        predicted_tat = clamp(
            float(predicted_tat),
            20,
            600
        )

        predicted_tat_minutes = int(
            round(predicted_tat)
        )

        # ----------------------------------------------------
        # Operational display range
        # ----------------------------------------------------
        #
        # MAE is used only as a simple +/- display range.
        # This is NOT a statistical confidence interval.
        # ----------------------------------------------------

        model_mae = read_model_mae()

        display_margin = max(
            1,
            int(round(model_mae))
        )

        lower_bound = max(
            20,
            predicted_tat_minutes
            - display_margin
        )

        upper_bound = min(
            600,
            predicted_tat_minutes
            + display_margin
        )

        return jsonify(
            {
                "success": True,

                "prediction": {

                    "predicted_tat_minutes":
                        predicted_tat_minutes,

                    "estimated_tat_range": {

                        "minimum_minutes":
                            lower_bound,

                        "maximum_minutes":
                            upper_bound
                    }
                },

                "candidate": {

                    "driver_id":
                        clean_integer(
                            data.get("driver_id")
                        ),

                    "truck_id":
                        clean_integer(
                            data.get("truck_id")
                        )
                },

                "input":
                    prediction_data,

                "model": {

                    "name":
                        "Random Forest Regressor",

                    "version":
                        MODEL_VERSION,

                    "target":
                        "actual_tat_minutes",

                    "model_file":
                        "delivery_tat_model.pkl",

                    "training_data":
                        "Synthetic / Generated",

                    "display_range_basis":
                        f"±{display_margin} minutes "
                        "using model MAE"
                }
            }
        )

    except Exception as error:

        return jsonify(
            {
                "success": False,
                "error": str(error)
            }
        ), 500


# ============================================================
# RUN SERVER
# ============================================================

if __name__ == "__main__":

    print()
    print("=" * 65)

    print(
        "Hong Sing DMS - ML Prediction API"
    )

    print("=" * 65)

    print()
    print(
        f"Model version: {MODEL_VERSION}"
    )

    print()
    print(
        "Model loaded successfully."
    )

    print()
    print(
        "Available endpoints:"
    )

    print(
        "  GET  /"
    )

    print(
        "  GET  /health"
    )

    print(
        "  POST /predict"
    )

    print()
    print(
        "Starting ML service..."
    )

    print(
        "URL: http://localhost:5000"
    )

    print()
    print(
        "Press CTRL+C to stop the service."
    )

    print()

    app.run(
        host="127.0.0.1",
        port=5000,
        debug=False
    )