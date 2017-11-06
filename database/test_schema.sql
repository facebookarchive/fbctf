-- MySQL dump 10.13  Distrib 5.5.44, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: fbctftests
-- ------------------------------------------------------
-- Server version	5.5.44-0ubuntu0.14.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `fbctftests`
--

/*!40000 DROP DATABASE IF EXISTS `fbctftests`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `fbctftests` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `fbctftests`;

--
-- Table structure for table `levels`
--

DROP TABLE IF EXISTS `levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `levels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `active` tinyint(1) NOT NULL,
  `type` varchar(4) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `entity_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `points` int(11) NOT NULL,
  `bonus` int(11) NOT NULL,
  `bonus_dec` int(11) NOT NULL,
  `bonus_fix` int(11) NOT NULL,
  `flag` text NOT NULL,
  `hint` text NOT NULL,
  `penalty` int(11) NOT NULL,
  `created_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `entity_id` (`entity_id`),
  KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(255) NOT NULL,
  `protected` tinyint(1) NOT NULL,
  `created_ts` timestamp NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `categories` WRITE;
INSERT INTO `categories` (category, created_ts, protected) VALUES("None", NOW(), 1);
INSERT INTO `categories` (category, created_ts, protected) VALUES("Quiz", NOW(), 1);
UNLOCK TABLES;

--
-- Table structure for table `attachments`
--

DROP TABLE IF EXISTS `attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level_id` int(11) NOT NULL,
  `filename` text NOT NULL,
  `type` text NOT NULL,
  `created_ts` timestamp NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `links`
--

DROP TABLE IF EXISTS `links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level_id` int(11) NOT NULL,
  `link` text NOT NULL,
  `created_ts` timestamp NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `teams`
--

DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `name` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `points` int(11) NOT NULL DEFAULT 0,
  `last_score` timestamp NOT NULL,
  `logo` text NOT NULL,
  `admin` tinyint(1) NOT NULL DEFAULT 0,
  `protected` tinyint(1) NOT NULL DEFAULT 0,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_ts` timestamp NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `visible` (`visible`),
  KEY `active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `livesync`
--

DROP TABLE IF EXISTS `livesync`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `livesync` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` text NOT NULL,
  `team_id` int(11) NOT NULL,
  `username` text NOT NULL,
  `sync_key` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `teams_oauth`
--

DROP TABLE IF EXISTS `teams_oauth`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams_oauth` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` text NOT NULL,
  `team_id` int(11) NOT NULL,
  `token` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `teams_data`
--

DROP TABLE IF EXISTS `teams_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `created_ts` timestamp NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `team_id` (`team_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cookie` varchar(200) NOT NULL,
  `data` text NOT NULL,
  `team_id` int(11) NOT NULL,
  `created_ts` timestamp NOT NULL DEFAULT 0,
  `last_access_ts` timestamp NOT NULL,
  `last_page_access` varchar(200) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `cookie` (`cookie`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `configuration`
--

DROP TABLE IF EXISTS `configuration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuration` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `field` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `field` (`field`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `configuration` WRITE;
INSERT INTO `configuration` (field, value, description) VALUES("game", "0", "(Boolean) Game is ongoing");
INSERT INTO `configuration` (field, value, description) VALUES("game_paused", "0", "(Boolean) Game is paused");
INSERT INTO `configuration` (field, value, description) VALUES("next_game", "0", "(Integer) Next game to happen");
INSERT INTO `configuration` (field, value, description) VALUES("game_duration_value", "3", "(Integer) Value of the duration of the game");
INSERT INTO `configuration` (field, value, description) VALUES("game_duration_unit", "h", "(Character) Unit of the duration of the game");
INSERT INTO `configuration` (field, value, description) VALUES("start_ts", "0", "(Integer) Timestamp of start");
INSERT INTO `configuration` (field, value, description) VALUES("end_ts", "0", "(Integer) Timestamp of end");
INSERT INTO `configuration` (field, value, description) VALUES("pause_ts", "0", "(Integer) Timestamp of pause");
INSERT INTO `configuration` (field, value, description) VALUES("timer", "0", "(Boolean) Timer is enabled");
INSERT INTO `configuration` (field, value, description) VALUES("scoring", "0", "(Boolean) Ability score levels");
INSERT INTO `configuration` (field, value, description) VALUES("gameboard", "1", "(Boolean) Refresh all data in the gameboard");
INSERT INTO `configuration` (field, value, description) VALUES("auto_announce", "0", "(Boolean) Auto game announcements");
INSERT INTO `configuration` (field, value, description) VALUES("progressive_cycle", "300", "(Integer) Frequency to take progressive scoreboard in seconds");
INSERT INTO `configuration` (field, value, description) VALUES("bases_cycle", "5", "(Integer) Frequency to score base levels in seconds");
INSERT INTO `configuration` (field, value, description) VALUES("autorun_cycle", "30", "(Integer) Frequency to cycle autorun in seconds");
INSERT INTO `configuration` (field, value, description) VALUES("gameboard_cycle", "5", "(Integer) Frequency to cycle gameboard in seconds");
INSERT INTO `configuration` (field, value, description) VALUES("conf_cycle", "10", "(Integer) Frequency to cycle configuration and commandline in seconds");
INSERT INTO `configuration` (field, value, description) VALUES("leaderboard_limit", "50", "(Integer) Maximum number of teams to show on the leaderboard");
INSERT INTO `configuration` (field, value, description) VALUES("registration", "0", "(Boolean) Ability to register teams");
INSERT INTO `configuration` (field, value, description) VALUES("registration_names", "0", "(Boolean) Registration will ask for names");
INSERT INTO `configuration` (field, value, description) VALUES("registration_type", "1", "(Integer) Type of registration: 1 - Open; 2 - Tokenized;");
INSERT INTO `configuration` (field, value, description) VALUES("registration_players", "3", "(Integer) Number of players per team");
INSERT INTO `configuration` (field, value, description) VALUES("registration_facebook", "0", "(Boolean) Allow Facebook Registration");
INSERT INTO `configuration` (field, value, description) VALUES("registration_google", "0", "(Boolean) Allow Google Registration");
INSERT INTO `configuration` (field, value, description) VALUES("registration_prefix", "Hacker", "(String) Automated Team Registation Name Prefix");
INSERT INTO `configuration` (field, value, description) VALUES("ldap", "0", "(Boolean) Ability to use LDAP to login");
INSERT INTO `configuration` (field, value, description) VALUES("ldap_server", "ldap://localhost", "(String) LDAP Server");
INSERT INTO `configuration` (field, value, description) VALUES("ldap_port", "389", "(Integer) LDAP Port");
INSERT INTO `configuration` (field, value, description) VALUES("ldap_domain_suffix", "@localhost", "(String) LDAP Domain");
INSERT INTO `configuration` (field, value, description) VALUES("login", "1", "(Boolean) Ability to login");
INSERT INTO `configuration` (field, value, description) VALUES("login_select", "0", "(Boolean) Login selecting the team");
INSERT INTO `configuration` (field, value, description) VALUES("login_strongpasswords", "0", "(Boolean) Enforce using strong passwords");
INSERT INTO `configuration` (field, value, description) VALUES("login_facebook", "0", "(Boolean) Allow Facebook Login");
INSERT INTO `configuration` (field, value, description) VALUES("login_google", "0", "(Boolean) Allow Google Login");
INSERT INTO `configuration` (field, value, description) VALUES("password_type", "1", "(Integer) Type of passwords: See table password_types");
INSERT INTO `configuration` (field, value, description) VALUES("default_bonus", "30", "(Integer) Default value for bonus in levels");
INSERT INTO `configuration` (field, value, description) VALUES("default_bonusdec", "10", "(Integer) Default bonus decrement in levels");
INSERT INTO `configuration` (field, value, description) VALUES("language", "en", "(String) Language of the system");
INSERT INTO `configuration` (field, value, description) VALUES("livesync", "0", "(Boolean) LiveSync functionality");
INSERT INTO `configuration` (field, value, description) VALUES("livesync_auth_key", "", "(String) Optional LiveSync Auth Key");
INSERT INTO `configuration` (field, value, description) VALUES("custom_logo", "0", "(Boolean) Custom branding logo");
INSERT INTO `configuration` (field, value, description) VALUES("custom_org", "Facebook", "(String) Custom branding organization text");
INSERT INTO `configuration` (field, value, description) VALUES("custom_byline", "Powered By Facebook", "(String) Custom branding byline text");
INSERT INTO `configuration` (field, value, description) VALUES("custom_logo_image", "static/img/favicon.png", "(String) Custom logo image file");
UNLOCK TABLES;

--
-- Table structure for table `password_types`
--

DROP TABLE IF EXISTS `password_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `field` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `field` (`field`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `password_types` WRITE;
INSERT INTO `password_types` (field, value, description) VALUES("1", "/.+/", "Length > 0");
INSERT INTO `password_types` (field, value, description) VALUES("2", "/.*^(?=.{8,})(?=.*[a-z])(?=.*[0-9]).*$/", "Length > 8, [a-z] and [0-9]");
INSERT INTO `password_types` (field, value, description) VALUES("3", "/.*^(?=.{8,})(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).*$/", "Length > 8, [a-z], [A-Z] and [0-9]");
INSERT INTO `password_types` (field, value, description) VALUES("4", "/.*^(?=.{8,})(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[\\W]+).*$/", "Length > 8, [a-z], [A-Z], [0-9] and Special chars");

UNLOCK TABLES;

--
-- Table structure for table `registration_log`
--

DROP TABLE IF EXISTS `registration_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `registration_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_name` text NOT NULL,
  `team_email` text NOT NULL,
  `team_logo` text NOT NULL,
  `team_password` text NOT NULL,
  `team_token` text NOT NULL,
  `ts` timestamp NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `registration_tokens`
--

DROP TABLE IF EXISTS `registration_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `registration_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token` varchar(250) NOT NULL,
  `used` tinyint(1) NOT NULL,
  `team_id` int(11) NOT NULL,
  `created_ts` timestamp NOT NULL DEFAULT 0,
  `use_ts` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `token` (`token`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `scores_log`
--

DROP TABLE IF EXISTS `scores_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scores_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ts` timestamp NOT NULL,
  `team_id` int(11) NOT NULL,
  `points` int(11) NOT NULL,
  `level_id` int(11) NOT NULL,
  `type` varchar(4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `level_id` (`level_id`),
  KEY `team_id` (`team_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bases_log`
--

DROP TABLE IF EXISTS `bases_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bases_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ts` timestamp NULL,
  `code` int(11) NOT NULL,
  `response` text NOT NULL,
  `level_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `level_id` (`level_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `scripts`
--

DROP TABLE IF EXISTS `scripts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scripts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `host` varchar(1024) NOT NULL,
  `ts` timestamp NULL,
  `pid` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `cmd` text NOT NULL,
  `status` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `host` (`host`),
  KEY `status` (`status`),
  KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `failures_log`
--

DROP TABLE IF EXISTS `failures_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failures_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ts` timestamp NOT NULL,
  `team_id` int(11) NOT NULL,
  `level_id` int(11) NOT NULL,
  `flag` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `team_id` (`team_id`),
  KEY `level_id` (`level_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hints_log`
--

DROP TABLE IF EXISTS `hints_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hints_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `level_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `penalty` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `level_id` (`level_id`),
  KEY `team_id` (`team_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;


--
-- Table structure for table `progressive_log`
--

DROP TABLE IF EXISTS `progressive_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `progressive_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ts` timestamp NOT NULL,
  `team_name` text NOT NULL,
  `points` int(11) NOT NULL,
  `iteration` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `announcements_log`
--

DROP TABLE IF EXISTS `announcements_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ts` timestamp NOT NULL,
  `announcement` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject` text NOT NULL,
  `action` text NOT NULL,
  `entity` text NOT NULL,
  `message` text NOT NULL,
  `arguments` text NOT NULL,
  `ts` timestamp NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
