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
  expired_at TEXT NOT NULL,
  last_seen_at TEXT,
  ip_address TEXT,
  user_agent TEXT
);

CREATE INDEX IF NOT EXISTS idx_admin_tokens_expired_at ON admin_tokens(expired_at);

CREATE TABLE IF NOT EXISTS auth_rate_limits (
  scope TEXT NOT NULL,
  identifier TEXT NOT NULL,
  attempts INTEGER NOT NULL DEFAULT 0,
  window_started_at TEXT NOT NULL,
  blocked_until TEXT,
  updated_at TEXT NOT NULL,
  PRIMARY KEY (scope, identifier)
);

CREATE INDEX IF NOT EXISTS idx_auth_rate_limits_updated_at ON auth_rate_limits(updated_at);

CREATE TABLE IF NOT EXISTS app_settings (
  setting_key TEXT PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_attendance_attend_date ON attendance(attend_date);
CREATE INDEX IF NOT EXISTS idx_attendance_location_status ON attendance(location_status);
CREATE INDEX IF NOT EXISTS idx_attendance_created_at ON attendance(created_at);
