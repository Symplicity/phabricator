CREATE DATABASE IF NOT EXISTS phabricator_shine;

CREATE TABLE IF NOT EXISTS phabricator_shine.shine_badge (
  `id` INT UNSIGNED NOT NULL auto_increment PRIMARY KEY,
  `title` VARCHAR(64) NOT NULL,
  `userPHID` VARCHAR(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `dateEarned` INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO phabricator_directory.directory_item
  (name, description, href, categoryID, sequence, dateCreated, dateModified)
VALUES
  ("Shine", "Badges! Awards! Kittens!", "/shine/", 5, 600,
    UNIX_TIMESTAMP(), UNIX_TIMESTAMP());