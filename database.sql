CREATE DATABASE IF NOT EXISTS manege_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE manege_db;

DROP TABLE IF EXISTS push_subscriptions;
DROP TABLE IF EXISTS outbox;
DROP TABLE IF EXISTS documents;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS reservation_cancellations;
DROP TABLE IF EXISTS instructor_availability;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS blocked_times;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS tracks;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('user','admin') DEFAULT 'user',
  insured BOOLEAN DEFAULT FALSE,
  email VARCHAR(120),
  phone VARCHAR(40),
  first_name VARCHAR(80),
  last_name VARCHAR(80),
  email_opt_in BOOLEAN DEFAULT TRUE,
  push_opt_in BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tracks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) UNIQUE NOT NULL,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  track_id INT NOT NULL,
  start_time DATETIME NOT NULL,
  end_time DATETIME NOT NULL,
  type ENUM('lesson','piste') DEFAULT 'piste',
  notes VARCHAR(255),
  rider_name VARCHAR(255),
  instructor_id INT NULL,
  status ENUM('active','canceled') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE,
  FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX(user_id), INDEX(track_id), INDEX(start_time), INDEX(end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE blocked_times (
  id INT AUTO_INCREMENT PRIMARY KEY,
  track_id INT NULL,
  start_time DATETIME NOT NULL,
  end_time DATETIME NOT NULL,
  reason VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE SET NULL,
  INDEX(start_time), INDEX(end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE instructor_availability (
  id INT AUTO_INCREMENT PRIMARY KEY,
  instructor_id INT NOT NULL,
  day_of_week INT NOT NULL COMMENT '0=Zondag, 1=Maandag, ..., 6=Zaterdag',
  start_time TIME NOT NULL COMMENT 'Bijvoorbeeld 14:00',
  end_time TIME NOT NULL COMMENT 'Bijvoorbeeld 18:00',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_schedule (instructor_id, day_of_week, start_time, end_time),
  FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX(instructor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reservation_cancellations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reservation_id INT NOT NULL,
  user_id INT NOT NULL,
  canceled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  late BOOLEAN NOT NULL DEFAULT FALSE,
  reason VARCHAR(500),
  requires_payment BOOLEAN NOT NULL DEFAULT FALSE,
  FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  path VARCHAR(255) NOT NULL,
  uploaded_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  event_date DATE NOT NULL,
  image_path VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE outbox (
  id INT AUTO_INCREMENT PRIMARY KEY,
  to_email VARCHAR(120) NOT NULL,
  subject VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  sent BOOLEAN DEFAULT FALSE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE push_subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  endpoint TEXT NOT NULL,
  p256dh VARCHAR(255) NOT NULL,
  auth    VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_endpoint (user_id, endpoint(255)),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Demo users in plaintext (worden bij eerste login automatisch gehashed)
INSERT INTO users (username,password,role,insured,email,first_name,last_name,phone) VALUES
('admin','admin','admin',1,'admin@manege.be','Site','Admin','0123456789'),
('jan','jan','user',0,'jan@manege.be','Jan','Jansen','0498765432');

-- Pistes
INSERT INTO tracks (name, sort_order) VALUES ('Piste A',1),('Piste B',2);

-- Voorbeeld reserveringen
INSERT INTO reservations (user_id,track_id,start_time,end_time,type,notes) VALUES
(2,1,'2025-10-05 10:00:00','2025-10-05 11:00:00','lesson','Dressuurles'),
(2,2,'2025-10-06 15:00:00','2025-10-06 16:00:00','piste','Vrij rijden');

-- Voorbeeld blokkade
INSERT INTO blocked_times (track_id,start_time,end_time,reason) VALUES (1,'2025-10-07 09:00:00','2025-10-07 12:00:00','Onderhoud piste A');

-- Voorbeeld evenement
INSERT INTO events (title,event_date,image_path) VALUES ('Jumping Wedstrijd','2025-11-01','uploads/events/jumping.png');
