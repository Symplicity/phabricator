CREATE DATABASE IF NOT EXISTS phabricator_shine;

CREATE TABLE IF NOT EXISTS phabricator_shine.shine_badge (
  `id` INT UNSIGNED NOT NULL auto_increment PRIMARY KEY,
  `title` VARCHAR(64) NOT NULL,
  `userPHID` VARCHAR(64) COLLATE utf8_bin NOT NULL,
  `dateEarned` INT UNSIGNED NOT NULL,
  `tally` int(10) unsigned NOT NULL DEFAULT '0',
  KEY `userPHID`(`userPHID`),
  KEY `title`(`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO phabricator_directory.directory_item
  (name, description, href, categoryID, sequence, dateCreated, dateModified)
VALUES
  ("Shine", "Badges! Awards! Kittens!", "/shine/", 5, 600,
    UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

CREATE TABLE IF NOT EXISTS phabricator_shine.shine_stats (
  `id` INT UNSIGNED NOT NULL auto_increment PRIMARY KEY,
  `dateLogged` INT UNSIGNED NOT NULL,
  `userPHID` VARCHAR(64) COLLATE utf8_bin NOT NULL,
  `title` VARCHAR(64) NOT NULL,
  `tally` int(10) unsigned NOT NULL DEFAULT '0',
  KEY `dateLogged`(`dateLogged`),
  KEY `userPHID`(`userPHID`),
  KEY `title`(`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
