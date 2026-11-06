-- Separate schema for the PHPUnit suite.
CREATE DATABASE IF NOT EXISTS wpkg_test CHARACTER SET utf8mb4;
GRANT ALL PRIVILEGES ON wpkg_test.* TO 'wpkg'@'%';
