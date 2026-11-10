import os
import sys

import pandas as pd
import joblib

from sklearn.model_selection import (
    train_test_split
)

from sklearn.compose import (
    ColumnTransformer
)

from sklearn.preprocessing import (
    OneHotEncoder
)

from sklearn.pipeline import (
    Pipeline
)

from sklearn.ensemble import (
    RandomForestRegressor
)

from sklearn.metrics import (
    mean_absolute_error,
    mean_squared_error,
    r2_score
)


# ============================================================
# HONG SING DMS
# ML MODEL TRAINING
# Delivery TAT Prediction
#
# Version:
# RF-TAT-v2-incidents
# ============================================================


BASE_DIR = os.path.dirname(
    os.path.abspath(__file__)
)

DATA_FILE = os.path.join(
    BASE_DIR,
    "data",
    "synthetic_delivery_training_data.csv"
)

MODEL_DIR = os.path.join(
    BASE_DIR,
    "models"
)

MODEL_FILE = os.path.join(
    MODEL_DIR,
    "delivery_tat_model.pkl"
)

METRICS_FILE = os.path.join(
    MODEL_DIR,
    "model_metrics.txt"
)


MODEL_VERSION = "RF-TAT-v2-incidents"

TARGET_COLUMN = (
    "actual_tat_minutes"
)


# ============================================================
# FEATURES
# ============================================================
#
# driver_id and truck_id are intentionally excluded.
#
# They are identifiers and should not be treated as numeric
# measures of driver/truck performance.
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


NUMERIC_FEATURES = [

    "weight_kg",

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


CATEGORICAL_FEATURES = [
    "priority_level",
]


def main():

    print("=" * 65)

    print(
        "Hong Sing DMS - ML Model Training"
    )

    print(
        "Delivery TAT Prediction"
    )

    print(
        f"Model Version: {MODEL_VERSION}"
    )

    print("=" * 65)

    print()

    # --------------------------------------------------------
    # Dataset
    # --------------------------------------------------------

    if not os.path.exists(DATA_FILE):

        print(
            "ERROR: Training dataset not found."
        )

        print(DATA_FILE)

        sys.exit(1)

    print(
        "Loading dataset:"
    )

    print(DATA_FILE)

    df = pd.read_csv(
        DATA_FILE
    )

    print()
    print(
        f"Dataset rows: {len(df)}"
    )

    print(
        f"Dataset columns: {len(df.columns)}"
    )

    # --------------------------------------------------------
    # Validate required columns
    # --------------------------------------------------------

    required_columns = (
        FEATURE_COLUMNS
        + [TARGET_COLUMN]
    )

    missing_columns = [

        column

        for column
        in required_columns

        if column not in df.columns
    ]

    if missing_columns:

        print()
        print(
            "ERROR: Missing required columns:"
        )

        for column in missing_columns:

            print(
                f"- {column}"
            )

        print()
        print(
            "Run generate_training_data.py "
            "before training the model."
        )

        sys.exit(1)

    # --------------------------------------------------------
    # Remove incomplete records
    # --------------------------------------------------------

    before_count = len(df)

    df = df.dropna(
        subset=required_columns
    )

    removed = (
        before_count - len(df)
    )

    if removed > 0:

        print()
        print(
            f"Removed {removed} rows "
            "containing missing values."
        )

    # --------------------------------------------------------
    # Validate target
    # --------------------------------------------------------

    df = df[
        (df[TARGET_COLUMN] >= 20)
        & (df[TARGET_COLUMN] <= 600)
    ]

    # --------------------------------------------------------
    # Validate rate features
    # --------------------------------------------------------

    rate_columns = [

        "driver_delay_rate",

        "driver_verified_violation_rate",

        "truck_breakdown_rate",
    ]

    for column in rate_columns:

        df = df[
            (df[column] >= 0)
            & (df[column] <= 1)
        ]

    if len(df) < 100:

        print()
        print(
            "ERROR: Not enough valid training records."
        )

        sys.exit(1)

    # --------------------------------------------------------
    # Features and target
    # --------------------------------------------------------

    X = df[
        FEATURE_COLUMNS
    ]

    y = df[
        TARGET_COLUMN
    ]

    print()
    print(
        "Target:"
    )

    print(
        f"  {TARGET_COLUMN}"
    )

    print()
    print(
        "Predictive Features:"
    )

    for feature in FEATURE_COLUMNS:

        print(
            f"  - {feature}"
        )

    print()
    print(
        "NOTE:"
    )

    print(
        "  driver_id and truck_id are metadata only "
        "and are NOT predictive features."
    )

    # --------------------------------------------------------
    # Train/test split
    # --------------------------------------------------------

    X_train, X_test, y_train, y_test = (
        train_test_split(

            X,
            y,

            test_size=0.20,

            random_state=42
        )
    )

    print()
    print(
        "Training/testing split:"
    )

    print(
        f"  Training records: {len(X_train)}"
    )

    print(
        f"  Testing records:  {len(X_test)}"
    )

    # ========================================================
    # PREPROCESSING
    # ========================================================

    preprocessor = ColumnTransformer(

        transformers=[

            (
                "categorical",

                OneHotEncoder(
                    handle_unknown="ignore"
                ),

                CATEGORICAL_FEATURES
            ),

            (
                "numeric",

                "passthrough",

                NUMERIC_FEATURES
            )
        ],

        remainder="drop"
    )

    # ========================================================
    # RANDOM FOREST REGRESSION
    # ========================================================

    model = RandomForestRegressor(

        n_estimators=300,

        max_depth=18,

        min_samples_split=4,

        min_samples_leaf=2,

        random_state=42,

        n_jobs=-1
    )

    pipeline = Pipeline(

        steps=[

            (
                "preprocessor",
                preprocessor
            ),

            (
                "model",
                model
            )
        ]
    )

    # ========================================================
    # TRAIN
    # ========================================================

    print()
    print("=" * 65)

    print(
        "TRAINING MODEL"
    )

    print("=" * 65)

    pipeline.fit(
        X_train,
        y_train
    )

    print()
    print(
        "Model training completed."
    )

    # --------------------------------------------------------
    # Evaluate
    # --------------------------------------------------------

    predictions = pipeline.predict(
        X_test
    )

    mae = mean_absolute_error(
        y_test,
        predictions
    )

    mse = mean_squared_error(
        y_test,
        predictions
    )

    rmse = mse ** 0.5

    r2 = r2_score(
        y_test,
        predictions
    )

    print()
    print("=" * 65)

    print(
        "MODEL EVALUATION"
    )

    print("=" * 65)

    print()
    print(
        f"MAE  : {mae:.2f} minutes"
    )

    print(
        f"RMSE : {rmse:.2f} minutes"
    )

    print(
        f"R²   : {r2:.4f}"
    )

    print()
    print(
        "Interpretation:"
    )

    print(
        "On this SYNTHETIC test dataset, "
        f"predictions differ from generated "
        f"TAT by an average of "
        f"{mae:.2f} minutes."
    )

    # --------------------------------------------------------
    # Avoid overstating synthetic model performance.
    # --------------------------------------------------------

    if r2 >= 0.90:

        fit_label = (
            "High fit on synthetic test data"
        )

    elif r2 >= 0.80:

        fit_label = (
            "Good fit on synthetic test data"
        )

    elif r2 >= 0.70:

        fit_label = (
            "Moderate fit on synthetic test data"
        )

    else:

        fit_label = (
            "Model requires further improvement"
        )

    print(
        f"Model result: {fit_label}"
    )

    # ========================================================
    # SAVE MODEL
    # ========================================================

    os.makedirs(
        MODEL_DIR,
        exist_ok=True
    )

    joblib.dump(
        pipeline,
        MODEL_FILE
    )

    print()
    print("=" * 65)

    print(
        "MODEL SAVED"
    )

    print("=" * 65)

    print(MODEL_FILE)

    # ========================================================
    # SAVE METRICS
    # ========================================================

    with open(
        METRICS_FILE,
        "w",
        encoding="utf-8"
    ) as file:

        file.write(
            "Hong Sing DMS - "
            "Delivery TAT Prediction Model\n"
        )

        file.write(
            f"Model Version: {MODEL_VERSION}\n"
        )

        file.write(
            "Dataset: SYNTHETIC / GENERATED DATA\n"
        )

        file.write(
            "IMPORTANT: Metrics are prototype results "
            "on generated data, not actual company "
            "historical performance.\n\n"
        )

        file.write(
            f"Training records: "
            f"{len(X_train)}\n"
        )

        file.write(
            f"Testing records: "
            f"{len(X_test)}\n"
        )

        file.write(
            f"MAE: {mae:.4f} minutes\n"
        )

        file.write(
            f"RMSE: {rmse:.4f} minutes\n"
        )

        file.write(
            f"R2: {r2:.4f}\n"
        )

        file.write(
            f"Interpretation: {fit_label}\n"
        )

        file.write(
            "\nPredictive Features:\n"
        )

        for feature in FEATURE_COLUMNS:

            file.write(
                f"- {feature}\n"
            )

        file.write(
            "\nExcluded identifiers:\n"
        )

        file.write(
            "- driver_id\n"
        )

        file.write(
            "- truck_id\n"
        )

    print()
    print(
        "Metrics saved to:"
    )

    print(
        METRICS_FILE
    )

    print()
    print("=" * 65)

    print(
        "TRAINING COMPLETE"
    )

    print("=" * 65)

    print()
    print(
        "IMPORTANT:"
    )

    print(
        "The model was trained using "
        "SYNTHETIC / GENERATED delivery data."
    )

    print(
        "Do not present these metrics as "
        "historical Hong Sing company performance."
    )

    print()


if __name__ == "__main__":
    main()