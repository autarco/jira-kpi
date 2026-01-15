-- MySQL server version: 5.7.39

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `issue`
(
    `key`       varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `type`      varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `created`   timestamp                            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `summary`   varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `parent`    varchar(255) COLLATE utf8_unicode_ci          DEFAULT NULL,
    `estimate`  smallint(6)                                   DEFAULT NULL,
    `status`    varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `cause_key` varchar(255) COLLATE utf8_unicode_ci          DEFAULT NULL,
    `category`  varchar(255) COLLATE utf8_unicode_ci          DEFAULT NULL
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

CREATE TABLE `transition`
(
    `external_id`  varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `issue`        varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `from`         varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `to`           varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `transitioned` timestamp                            NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

ALTER TABLE `issue`
    ADD PRIMARY KEY (`key`);

ALTER TABLE `transition`
    ADD PRIMARY KEY (`external_id`),
    ADD KEY `issue` (`issue`);

ALTER TABLE `transition`
    ADD CONSTRAINT `transition_fk1` FOREIGN KEY (`issue`) REFERENCES `issue` (`key`);

COMMIT;
