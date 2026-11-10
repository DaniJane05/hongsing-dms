import csv
import math
import os
import random
import re
from datetime import datetime

import mysql.connector

# ============================================================
# HONG SING DMS
# Synthetic ML Training Dataset Generator
# RF-TAT-v2-incidents
# ============================================================
# IMPORTANT:
# This generator creates SYNTHETIC / GENERATED training data.
# It must NOT be represented as actual Hong Sing historical data.
# ============================================================

DB_HOST = "127.0.0.1"
DB_PORT = 3306
DB_NAME = "hongsing_dms"
DB_USER = "root"
DB_PASSWORD = ""

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
OUTPUT_DIR = os.path.join(BASE_DIR, "data")
OUTPUT_FILE = os.path.join(OUTPUT_DIR, "synthetic_delivery_training_data.csv")

NUM_RECORDS = 2000
RANDOM_SEED = 2026
random.seed(RANDOM_SEED)

SYNTHETIC_DRIVERS = [
    {"driver_id": -1, "full_name": "Synthetic Driver 01", "rating": 4.90, "total_deliveries": 420},
    {"driver_id": -2, "full_name": "Synthetic Driver 02", "rating": 4.70, "total_deliveries": 330},
    {"driver_id": -3, "full_name": "Synthetic Driver 03", "rating": 4.50, "total_deliveries": 250},
    {"driver_id": -4, "full_name": "Synthetic Driver 04", "rating": 4.30, "total_deliveries": 180},
    {"driver_id": -5, "full_name": "Synthetic Driver 05", "rating": 4.10, "total_deliveries": 120},
    {"driver_id": -6, "full_name": "Synthetic Driver 06", "rating": 3.90, "total_deliveries": 70},
]

SYNTHETIC_TRUCKS = [
    {"truck_id": -1, "plate_number": "SYN-TRK-01", "capacity": 1000.0},
    {"truck_id": -2, "plate_number": "SYN-TRK-02", "capacity": 1500.0},
    {"truck_id": -3, "plate_number": "SYN-TRK-03", "capacity": 2000.0},
    {"truck_id": -4, "plate_number": "SYN-TRK-04", "capacity": 2500.0},
    {"truck_id": -5, "plate_number": "SYN-TRK-05", "capacity": 3000.0},
    {"truck_id": -6, "plate_number": "SYN-TRK-06", "capacity": 4000.0},
    {"truck_id": -7, "plate_number": "SYN-TRK-07", "capacity": 5000.0},
]


def clamp(value, minimum, maximum):
    return max(minimum, min(value, maximum))


def safe_float(value, default=0.0):
    try:
        if value is None:
            return default
        return float(value)
    except (TypeError, ValueError):
        return default


def safe_int(value, default=0):
    try:
        if value is None:
            return default
        return int(float(value))
    except (TypeError, ValueError):
        return default


def parse_route_time(value, distance_km):
    text = str(value or "").strip().lower()
    hours = 0.0
    minutes = 0.0

    hour_match = re.search(r"(\d+(?:\.\d+)?)\s*(?:hours?|hrs?|hr|h)\b", text)
    minute_match = re.search(r"(\d+(?:\.\d+)?)\s*(?:minutes?|mins?|min|m)\b", text)

    if hour_match:
        hours = float(hour_match.group(1))
    if minute_match:
        minutes = float(minute_match.group(1))

    total_minutes = (hours * 60) + minutes

    if total_minutes <= 0:
        numeric_match = re.search(r"\b(\d+(?:\.\d+)?)\b", text)
        if numeric_match:
            total_minutes = float(numeric_match.group(1))

    if total_minutes <= 0:
        total_minutes = (distance_km / 25.0) * 60.0

    return int(round(clamp(total_minutes, 5, 240)))


def generate_route_scenarios():
    routes = []
    distance_ranges = [(2, 8), (5, 15), (10, 25), (18, 35), (25, 45), (35, 60)]

    for index in range(100):
        low, high = random.choice(distance_ranges)
        distance = round(random.uniform(low, high), 2)
        speed_kmh = random.uniform(20, 35)
        base_minutes = (distance / speed_kmh) * 60
        traffic_factor = random.uniform(1.00, 1.45)
        estimated_minutes = int(round(base_minutes * traffic_factor))
        estimated_minutes = int(clamp(estimated_minutes, 5, 180))
        routes.append({
            "route_id": -(index + 1),
            "route_code": f"SYN-ROUTE-{index + 1:03d}",
            "distance_km": distance,
            "estimated_time_minutes": estimated_minutes,
            "is_synthetic_route": True,
        })

    return routes


def load_database_data():
    connection = None
    try:
        connection = mysql.connector.connect(
            host=DB_HOST,
            port=DB_PORT,
            database=DB_NAME,
            user=DB_USER,
            password=DB_PASSWORD,
        )
        cursor = connection.cursor(dictionary=True)

        cursor.execute("""
            SELECT driver_id, full_name, rating, total_deliveries, status
            FROM driver
            WHERE status <> 'Inactive'
            ORDER BY driver_id
        """)
        drivers = cursor.fetchall()

        cursor.execute("""
            SELECT truck_id, plate_number, capacity, status, current_load
            FROM truck
            WHERE status <> 'Maintenance'
            ORDER BY truck_id
        """)
        trucks = cursor.fetchall()

        cursor.execute("""
            SELECT route_id, route_code, estimated_distance, estimated_time
            FROM route
            ORDER BY route_id
        """)
        routes = cursor.fetchall()
        cursor.close()
        return drivers, trucks, routes
    finally:
        if connection is not None and connection.is_connected():
            connection.close()


def normalize_drivers(database_drivers):
    drivers = []
    for driver in database_drivers:
        rating = safe_float(driver.get("rating"), 4.20)
        if rating <= 0:
            rating = random.uniform(3.8, 4.8)
        drivers.append({
            "driver_id": safe_int(driver.get("driver_id")),
            "full_name": driver.get("full_name", "Existing Driver"),
            "rating": round(clamp(rating, 3.0, 5.0), 2),
            "total_deliveries": max(0, safe_int(driver.get("total_deliveries"), 50)),
            "is_synthetic_driver": False,
        })

    for driver in SYNTHETIC_DRIVERS:
        drivers.append({**driver, "is_synthetic_driver": True})
    return drivers


def normalize_trucks(database_trucks):
    trucks = []
    for truck in database_trucks:
        capacity = max(100, safe_float(truck.get("capacity"), 1000))
        current_load = clamp(safe_float(truck.get("current_load"), 0), 0, capacity)
        trucks.append({
            "truck_id": safe_int(truck.get("truck_id")),
            "plate_number": truck.get("plate_number", "Existing Truck"),
            "capacity": round(capacity, 2),
            "current_load": round(current_load, 2),
            "is_synthetic_truck": False,
        })

    for truck in SYNTHETIC_TRUCKS:
        trucks.append({**truck, "current_load": 0.0, "is_synthetic_truck": True})
    return trucks


def normalize_routes(database_routes):
    routes = []
    for route in database_routes:
        distance = safe_float(route.get("estimated_distance"), 0)
        if distance <= 0:
            continue
        routes.append({
            "route_id": safe_int(route.get("route_id")),
            "route_code": route.get("route_code", "DB-ROUTE"),
            "distance_km": round(clamp(distance, 1, 80), 2),
            "estimated_time_minutes": parse_route_time(route.get("estimated_time"), distance),
            "is_synthetic_route": False,
        })

    routes.extend(generate_route_scenarios())
    return routes


def choose_driver(drivers):
    return random.choice(drivers)


def choose_truck(trucks, weight_kg):
    eligible = [
        truck for truck in trucks
        if (truck["capacity"] - truck["current_load"]) >= weight_kg
    ]
    if not eligible:
        return None
    eligible.sort(key=lambda truck: truck["capacity"] - weight_kg)
    return random.choice(eligible[:min(3, len(eligible))])


def generate_delivery_weight(trucks):
    max_capacity = max(truck["capacity"] - truck["current_load"] for truck in trucks)
    pattern = random.random()

    if pattern < 0.20:
        weight = random.uniform(5, min(250, max_capacity))
    elif pattern < 0.50:
        weight = random.uniform(min(200, max_capacity), min(1200, max_capacity))
    elif pattern < 0.80:
        weight = random.uniform(min(700, max_capacity), min(2500, max_capacity))
    else:
        weight = random.uniform(min(1200, max_capacity), max_capacity)

    return round(clamp(weight, 5, max_capacity), 2)


def generate_priority():
    return "Urgent" if random.random() < 0.25 else "Normal"


def generate_driver_incident_profile(driver):
    """Generate synthetic historical driver incident rates for prototype training."""
    rating = safe_float(driver.get("rating"), 4.20)
    total_deliveries = safe_int(driver.get("total_deliveries"), 0)
    experience_factor = min(total_deliveries / 400.0, 1.0)
    rating_factor = clamp((5.0 - rating) / 2.0, 0.0, 1.0)

    delay_rate = (
        0.03
        + (rating_factor * 0.12)
        - (experience_factor * 0.02)
        + random.uniform(-0.015, 0.025)
    )
    driver_delay_rate = round(clamp(delay_rate, 0.01, 0.25), 4)
    violation_rate = driver_delay_rate * random.uniform(0.05, 0.30)

    return {
        "driver_delay_rate": driver_delay_rate,
        "driver_verified_violation_rate": round(clamp(violation_rate, 0.0, 0.08), 4),
    }


def generate_truck_breakdown_rate(_truck):
    """Generate a synthetic historical truck breakdown rate."""
    return round(clamp(random.uniform(0.005, 0.08), 0.0, 0.15), 4)


def calculate_actual_tat(
    weight_kg,
    priority_level,
    route_distance_km,
    estimated_route_time_minutes,
    driver_rating,
    driver_total_deliveries,
    driver_delay_rate,
    driver_verified_violation_rate,
    truck_capacity_kg,
    truck_current_load_kg,
    truck_breakdown_rate,
):
    route_component = estimated_route_time_minutes
    distance_component = route_distance_km * random.uniform(0.25, 0.60)
    handling_component = 10 + (weight_kg * 0.018)

    if truck_capacity_kg > 0:
        load_before_ratio = truck_current_load_kg / truck_capacity_kg
        delivery_load_ratio = weight_kg / truck_capacity_kg
    else:
        load_before_ratio = 0
        delivery_load_ratio = 0

    utilization_component = (load_before_ratio + delivery_load_ratio) * random.uniform(8, 18)
    rating_effect = (4.5 - driver_rating) * random.uniform(5, 9)
    experience_score = math.log1p(max(0, driver_total_deliveries))
    experience_effect = (5.8 - experience_score) * random.uniform(1.2, 2.0)

    delay_history_effect = driver_delay_rate * random.uniform(35, 65)
    violation_history_effect = driver_verified_violation_rate * random.uniform(45, 90)
    breakdown_history_effect = truck_breakdown_rate * random.uniform(40, 80)

    priority_effect = random.uniform(-14, -6) if priority_level == "Urgent" else random.uniform(0, 4)
    traffic_factor = random.uniform(0.92, 1.22)
    traffic_component = route_component * (traffic_factor - 1.0)
    operational_noise = random.gauss(0, 8)

    raw_tat = (
        route_component
        + distance_component
        + handling_component
        + utilization_component
        + rating_effect
        + experience_effect
        + delay_history_effect
        + violation_history_effect
        + breakdown_history_effect
        + priority_effect
        + traffic_component
        + operational_noise
    )
    return int(round(clamp(raw_tat, 20, 600)))


def generate_record(index, drivers, trucks, routes):
    for _ in range(100):
        route = random.choice(routes)
        distance_km = route["distance_km"]
        estimated_route_time_minutes = route["estimated_time_minutes"]
        weight_kg = generate_delivery_weight(trucks)
        truck = choose_truck(trucks, weight_kg)
        if truck is None:
            continue

        driver = choose_driver(drivers)
        priority_level = generate_priority()
        incident_profile = generate_driver_incident_profile(driver)
        driver_delay_rate = incident_profile["driver_delay_rate"]
        driver_verified_violation_rate = incident_profile["driver_verified_violation_rate"]
        truck_breakdown_rate = generate_truck_breakdown_rate(truck)

        actual_tat_minutes = calculate_actual_tat(
            weight_kg=weight_kg,
            priority_level=priority_level,
            route_distance_km=distance_km,
            estimated_route_time_minutes=estimated_route_time_minutes,
            driver_rating=driver["rating"],
            driver_total_deliveries=driver["total_deliveries"],
            driver_delay_rate=driver_delay_rate,
            driver_verified_violation_rate=driver_verified_violation_rate,
            truck_capacity_kg=truck["capacity"],
            truck_current_load_kg=truck["current_load"],
            truck_breakdown_rate=truck_breakdown_rate,
        )

        return {
            "synthetic_order_ref": f"SYN-ML-{datetime.now().strftime('%Y%m%d')}-{index:05d}",
            "driver_id": driver["driver_id"],
            "truck_id": truck["truck_id"],
            "weight_kg": round(weight_kg, 2),
            "priority_level": priority_level,
            "route_distance_km": round(distance_km, 2),
            "estimated_route_time_minutes": int(estimated_route_time_minutes),
            "driver_rating": round(driver["rating"], 2),
            "driver_total_deliveries": int(driver["total_deliveries"]),
            "driver_delay_rate": round(driver_delay_rate, 4),
            "driver_verified_violation_rate": round(driver_verified_violation_rate, 4),
            "truck_capacity_kg": round(truck["capacity"], 2),
            "truck_current_load_kg": round(truck["current_load"], 2),
            "truck_breakdown_rate": round(truck_breakdown_rate, 4),
            "actual_tat_minutes": int(actual_tat_minutes),
            "is_synthetic": 1,
        }

    raise RuntimeError("Unable to generate a valid delivery record after multiple attempts.")


def validate_record(record):
    weight = safe_float(record["weight_kg"])
    capacity = safe_float(record["truck_capacity_kg"])
    current_load = safe_float(record["truck_current_load_kg"])
    distance = safe_float(record["route_distance_km"])
    route_time = safe_int(record["estimated_route_time_minutes"])
    tat = safe_int(record["actual_tat_minutes"])
    rating = safe_float(record["driver_rating"])
    delay_rate = safe_float(record["driver_delay_rate"])
    violation_rate = safe_float(record["driver_verified_violation_rate"])
    breakdown_rate = safe_float(record["truck_breakdown_rate"])

    return all([
        weight > 0,
        weight <= (capacity - current_load),
        distance > 0,
        route_time > 0,
        20 <= tat <= 600,
        3.0 <= rating <= 5.0,
        0.0 <= delay_rate <= 1.0,
        0.0 <= violation_rate <= 1.0,
        0.0 <= breakdown_rate <= 1.0,
        record["priority_level"] in ("Normal", "Urgent"),
        record["is_synthetic"] == 1,
    ])


def save_csv(records):
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    fieldnames = [
        "synthetic_order_ref",
        "driver_id",
        "truck_id",
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
        "actual_tat_minutes",
        "is_synthetic",
    ]

    with open(OUTPUT_FILE, "w", newline="", encoding="utf-8") as csvfile:
        writer = csv.DictWriter(csvfile, fieldnames=fieldnames)
        writer.writeheader()
        writer.writerows(records)


def print_summary(records, drivers, trucks, routes):
    weights = [r["weight_kg"] for r in records]
    distances = [r["route_distance_km"] for r in records]
    tat_values = [r["actual_tat_minutes"] for r in records]
    delay_rates = [r["driver_delay_rate"] for r in records]
    violation_rates = [r["driver_verified_violation_rate"] for r in records]
    breakdown_rates = [r["truck_breakdown_rate"] for r in records]

    print()
    print("=" * 65)
    print("DATASET GENERATED SUCCESSFULLY")
    print("=" * 65)
    print(f"Total records: {len(records)}")
    print(f"Average weight: {sum(weights) / len(weights):.2f} kg")
    print(f"Average distance: {sum(distances) / len(distances):.2f} km")
    print(f"Average actual TAT: {sum(tat_values) / len(tat_values):.2f} minutes")
    print(f"Average synthetic driver delay rate: {(sum(delay_rates) / len(delay_rates)) * 100:.2f}%")
    print(f"Average synthetic verified violation rate: {(sum(violation_rates) / len(violation_rates)) * 100:.2f}%")
    print(f"Average synthetic truck breakdown rate: {(sum(breakdown_rates) / len(breakdown_rates)) * 100:.2f}%")
    print(f"Training drivers available: {len(drivers)}")
    print(f"Training trucks available: {len(trucks)}")
    print(f"Training routes available: {len(routes)}")
    print(f"CSV saved to: {OUTPUT_FILE}")
    print()
    print("IMPORTANT: This dataset is SYNTHETIC / GENERATED DATA.")
    print("Do not represent it as actual historical Hong Sing deliveries or incidents.")
    print("=" * 65)


def main():
    print("=" * 65)
    print("Hong Sing DMS - Synthetic ML Dataset Generator")
    print("RF-TAT-v2-incidents")
    print("=" * 65)
    print(f"Records to generate: {NUM_RECORDS}")
    print("Connecting to MySQL...")

    try:
        database_drivers, database_trucks, database_routes = load_database_data()
    except mysql.connector.Error as error:
        print(f"ERROR: Could not connect to MySQL: {error}")
        print("Make sure XAMPP MySQL is running and the database settings are correct.")
        return

    drivers = normalize_drivers(database_drivers)
    trucks = normalize_trucks(database_trucks)
    routes = normalize_routes(database_routes)

    if not drivers:
        raise RuntimeError("No driver profiles are available for training-data generation.")
    if not trucks:
        raise RuntimeError("No truck profiles are available for training-data generation.")
    if not routes:
        routes = generate_route_scenarios()

    print(f"Database drivers: {len(database_drivers)}")
    print(f"Database trucks: {len(database_trucks)}")
    print(f"Database routes: {len(database_routes)}")
    print(f"Training drivers: {len(drivers)}")
    print(f"Training trucks: {len(trucks)}")
    print(f"Training routes: {len(routes)}")
    print("Generating synthetic delivery records...")

    records = []
    for index in range(1, NUM_RECORDS + 1):
        record = generate_record(index, drivers, trucks, routes)
        if not validate_record(record):
            raise RuntimeError(f"Generated invalid record at row {index}.")
        records.append(record)
        if index % 250 == 0:
            print(f"Generated {index}/{NUM_RECORDS} records...")

    save_csv(records)
    print_summary(records, drivers, trucks, routes)


if __name__ == "__main__":
    main()
