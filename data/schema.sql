CREATE TABLE IF NOT EXISTS attendance (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  student_no TEXT NOT NULL,
  name TEXT NOT NULL,
  attend_date TEXT NOT NULL,
  created_at TEXT NOT NULL,
  location_status TEXT NOT NULL DEFAULT 'unchecked',
  location_latitude REAL,
  location_longitude REAL,
  location_accuracy REAL,
  location_distance_meters REAL,
  location_message TEXT,
  location_checked_at TEXT,
  location_approved_at TEXT,
  UNIQUE(student_no, attend_date)
);

CREATE TABLE IF NOT EXISTS admin (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  password_hash TEXT NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS admin_tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  token TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL,
  expired_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS app_settings (
  setting_key TEXT PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
