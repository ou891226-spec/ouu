-- MariaDB dump 10.19  Distrib 10.4.24-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: myproject
-- ------------------------------------------------------
-- Server version	10.4.24-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `daily_tasks`
--

DROP TABLE IF EXISTS `daily_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_tasks` (
  `task_id` int(11) NOT NULL AUTO_INCREMENT,
  `task_name` varchar(50) NOT NULL,
  `task_description` text NOT NULL,
  `task_type` varchar(20) NOT NULL,
  `reward_points` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`task_id`)
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_tasks`
--

LOCK TABLES `daily_tasks` WRITE;
/*!40000 ALTER TABLE `daily_tasks` DISABLE KEYS */;
INSERT INTO `daily_tasks` VALUES (1,'成就任務','遊玩任一普通關卡一次','Achievement',50,1),(2,'成就任務','完成任意一場遊戲對戰','Achievement',50,1),(3,'好友任務','好友組隊遊戲一次','friend',50,1),(47,'成就任務','完成三種不同類型遊戲\r\n','Achievement',50,1),(48,'成就任務','打破自己遊戲最高分','Achievement',50,1),(49,'好友任務','與好友對戰並取得勝利','friend',50,1),(50,'好友任務','新增 1 位新好友','friend',50,1),(51,'好友任務','與好友同時在線超過30分鐘','friend',50,1),(52,'登入網站一次','今天登入網站一次即可完成任務','login',10,1);
/*!40000 ALTER TABLE `daily_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `difficulty_settings`
--

DROP TABLE IF EXISTS `difficulty_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `difficulty_settings` (
  `game_id` int(11) NOT NULL,
  `difficulty_id` int(11) NOT NULL,
  `difficulty` varchar(10) NOT NULL,
  `time_limit` int(11) DEFAULT NULL,
  `points_per_correct` int(11) DEFAULT NULL,
  `pass_score` int(11) NOT NULL,
  `pass_bounce` int(11) NOT NULL,
  PRIMARY KEY (`game_id`,`difficulty_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `difficulty_settings`
--

LOCK TABLES `difficulty_settings` WRITE;
/*!40000 ALTER TABLE `difficulty_settings` DISABLE KEYS */;
INSERT INTO `difficulty_settings` VALUES (1,1,'easy',60,2,20,20),(1,2,'normal',50,3,30,50),(1,3,'hard',40,5,50,100),(2,1,'easy',60,NULL,200,20),(2,2,'normal',60,NULL,450,50),(2,3,'hard',60,NULL,600,100),(3,1,'easy',60,3,0,0),(3,2,'normal',60,3,0,0),(3,3,'hard',60,3,0,0),(4,1,'easy',NULL,NULL,1500,20),(4,2,'normal',NULL,NULL,5000,50),(4,3,'hard',NULL,NULL,10000,100),(5,1,'easy',60,1,0,20),(5,2,'normal',120,2,0,50),(5,3,'hard',180,2,0,100),(6,1,'easy',60,2,0,20),(6,2,'normal',60,2,0,50),(6,3,'hard',120,2,0,100),(7,1,'easy',60,10,1200,20),(7,2,'normal',60,10,800,50),(7,3,'hard',60,10,300,100);
/*!40000 ALTER TABLE `difficulty_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `friend_requests`
--

DROP TABLE IF EXISTS `friend_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `friend_requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`request_id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  CONSTRAINT `friend_requests_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `member` (`member_id`),
  CONSTRAINT `friend_requests_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `member` (`member_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `friend_requests`
--

LOCK TABLES `friend_requests` WRITE;
/*!40000 ALTER TABLE `friend_requests` DISABLE KEYS */;
INSERT INTO `friend_requests` VALUES (2,8,6,'rejected','2025-05-22 18:08:39'),(3,8,7,'accepted','2025-05-22 18:08:56'),(4,8,6,'accepted','2025-05-22 18:32:44'),(5,11,10,'accepted','2025-05-27 12:18:46'),(6,7,11,'rejected','2025-05-28 15:15:15'),(7,11,7,'pending','2025-05-28 15:29:28'),(8,20,21,'pending','2025-05-28 16:38:40'),(9,8,6,'pending','2025-07-09 16:20:44');
/*!40000 ALTER TABLE `friend_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `friends`
--

DROP TABLE IF EXISTS `friends`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `friends` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `friend_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_friendship` (`member_id`,`friend_id`),
  KEY `friend_id` (`friend_id`),
  CONSTRAINT `friends_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `member` (`member_id`),
  CONSTRAINT `friends_ibfk_2` FOREIGN KEY (`friend_id`) REFERENCES `member` (`member_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `friends`
--

LOCK TABLES `friends` WRITE;
/*!40000 ALTER TABLE `friends` DISABLE KEYS */;
INSERT INTO `friends` VALUES (1,7,8,'2025-05-22 18:21:13'),(2,8,7,'2025-05-22 18:21:13'),(3,6,8,'2025-05-22 18:33:20'),(4,8,6,'2025-05-22 18:33:20'),(5,10,11,'2025-05-27 12:23:00'),(6,11,10,'2025-05-27 12:23:00');
/*!40000 ALTER TABLE `friends` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `game_high_scores`
--

DROP TABLE IF EXISTS `game_high_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `game_high_scores` (
  `member_id` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  `difficulty_level` varchar(10) NOT NULL,
  `high_score` int(11) DEFAULT 0,
  PRIMARY KEY (`member_id`,`game_id`,`difficulty_level`),
  KEY `game_id` (`game_id`),
  CONSTRAINT `game_high_scores_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `member` (`member_id`),
  CONSTRAINT `game_high_scores_ibfk_2` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `game_high_scores`
--

LOCK TABLES `game_high_scores` WRITE;
/*!40000 ALTER TABLE `game_high_scores` DISABLE KEYS */;
INSERT INTO `game_high_scores` VALUES (6,1,'easy',24),(7,1,'easy',14),(7,1,'normal',80),(8,1,'easy',144),(8,1,'hard',58),(8,1,'normal',90),(10,1,'easy',8),(10,1,'normal',20);
/*!40000 ALTER TABLE `game_high_scores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `game_records`
--

DROP TABLE IF EXISTS `game_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `game_records` (
  `record_id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) DEFAULT NULL,
  `game_id` int(11) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `difficulty` varchar(10) DEFAULT NULL,
  `play_date` datetime DEFAULT NULL,
  `play_time` int(11) DEFAULT NULL,
  `game_type` varchar(20) DEFAULT NULL,
  `is_single_player` tinyint(1) DEFAULT NULL,
  `opponent_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`record_id`),
  KEY `member_id` (`member_id`),
  KEY `game_id` (`game_id`),
  CONSTRAINT `game_records_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `member` (`member_id`) ON DELETE CASCADE,
  CONSTRAINT `game_records_ibfk_2` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=434 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `game_records`
--

LOCK TABLES `game_records` WRITE;
/*!40000 ALTER TABLE `game_records` DISABLE KEYS */;
INSERT INTO `game_records` VALUES (62,8,1,0,'easy','2025-05-10 23:59:11',16,'反應力',1,NULL),(63,8,1,28,'easy','2025-05-11 21:11:38',14,'反應力',1,NULL),(64,8,1,138,'easy','2025-05-12 20:18:31',60,'反應力',1,NULL),(65,8,1,0,'hard','2025-05-12 21:15:40',4,'反應力',1,NULL),(66,8,1,0,'easy','2025-05-12 21:16:08',5,'反應力',1,NULL),(67,8,1,4,'normal','2025-05-12 21:16:15',3,'反應力',1,NULL),(68,8,5,20,'easy','2025-05-22 18:20:53',NULL,'記憶力',1,NULL),(69,8,5,20,'easy','2025-05-22 18:41:16',29,'記憶力',1,NULL),(70,8,5,0,'easy','2025-05-22 18:41:35',3,'記憶力',1,NULL),(71,8,6,20,'easy','2025-05-26 19:43:30',49,'接金蛋',1,NULL),(110,8,1,0,'hard','2025-05-26 21:52:57',1,'反應力',1,NULL),(124,8,1,40,'easy','2025-05-26 22:32:01',18,'反應力',1,NULL),(131,NULL,6,NULL,NULL,'2025-05-26 22:48:50',NULL,'接金蛋',1,NULL),(132,8,6,20,'easy','2025-05-26 22:49:51',30,'接金蛋',1,NULL),(133,NULL,6,NULL,NULL,'2025-05-26 22:50:19',NULL,'接金蛋',1,NULL),(134,8,6,20,'easy','2025-05-26 22:52:40',19,'記憶力',1,NULL),(135,NULL,6,NULL,NULL,'2025-05-26 22:54:11',NULL,'記憶力',1,NULL),(136,8,6,20,'easy','2025-05-26 22:54:45',30,'記憶力',1,NULL),(137,NULL,6,NULL,NULL,'2025-05-26 22:54:52',NULL,'記憶力',1,NULL),(138,8,6,20,'easy','2025-05-26 22:55:30',36,'記憶力',1,NULL),(139,8,6,20,'easy','2025-05-26 22:57:30',41,'記憶力',1,NULL),(140,NULL,6,NULL,NULL,'2025-05-26 22:57:49',NULL,'記憶力',1,NULL),(141,8,6,20,'easy','2025-05-26 22:58:15',23,'記憶力',1,NULL),(142,NULL,6,NULL,NULL,'2025-05-26 23:01:41',NULL,'記憶力',1,NULL),(148,8,6,20,'easy','2025-05-27 09:19:18',24,'記憶力',1,NULL),(149,NULL,6,NULL,NULL,'2025-05-27 09:19:30',NULL,'記憶力',1,NULL),(150,8,6,20,'easy','2025-05-27 09:19:55',23,'記憶力',1,NULL),(151,NULL,6,NULL,NULL,'2025-05-27 09:20:52',NULL,'記憶力',1,NULL),(152,NULL,6,NULL,NULL,'2025-05-27 09:21:27',NULL,'記憶力',1,NULL),(153,NULL,6,NULL,NULL,'2025-05-27 09:21:59',NULL,'記憶力',1,NULL),(156,NULL,6,NULL,NULL,'2025-05-27 09:23:15',NULL,'記憶力',1,NULL),(157,NULL,6,NULL,NULL,'2025-05-27 09:23:40',NULL,'記憶力',1,NULL),(158,NULL,6,NULL,NULL,'2025-05-27 09:44:49',NULL,'記憶力',1,NULL),(159,NULL,6,NULL,NULL,'2025-05-27 09:45:01',NULL,'記憶力',1,NULL),(160,NULL,6,NULL,NULL,'2025-05-27 09:45:09',NULL,'記憶力',1,NULL),(161,NULL,6,NULL,NULL,'2025-05-27 09:45:17',NULL,'記憶力',1,NULL),(162,NULL,6,NULL,NULL,'2025-05-27 12:14:09',NULL,'記憶力',1,NULL),(163,NULL,6,NULL,NULL,'2025-05-27 12:14:11',NULL,'記憶力',1,NULL),(164,NULL,6,NULL,NULL,'2025-05-27 12:14:17',NULL,'記憶力',1,NULL),(165,NULL,6,NULL,NULL,'2025-05-27 12:14:26',NULL,'記憶力',1,NULL),(166,8,6,0,'easy','2025-05-27 12:15:20',48,'記憶力',1,NULL),(167,NULL,6,NULL,NULL,'2025-05-27 12:16:21',NULL,'記憶力',1,NULL),(169,NULL,6,NULL,NULL,'2025-05-27 12:17:30',NULL,'記憶力',1,NULL),(170,NULL,6,NULL,NULL,'2025-05-27 12:17:55',NULL,'記憶力',1,NULL),(171,NULL,6,NULL,NULL,'2025-05-27 12:18:36',NULL,'記憶力',1,NULL),(172,NULL,6,NULL,NULL,'2025-05-27 12:29:15',NULL,'記憶力',1,NULL),(173,NULL,6,NULL,NULL,'2025-05-27 12:30:02',NULL,'記憶力',1,NULL),(174,NULL,6,NULL,NULL,'2025-05-27 12:30:11',NULL,'記憶力',1,NULL),(175,NULL,6,NULL,NULL,'2025-05-27 12:30:35',NULL,'記憶力',1,NULL),(176,NULL,6,NULL,NULL,'2025-05-27 12:33:12',NULL,'記憶力',1,NULL),(177,10,1,8,'easy','2025-05-27 12:33:22',4,'反應力',1,NULL),(178,NULL,6,NULL,NULL,'2025-05-27 12:34:06',NULL,'記憶力',1,NULL),(179,10,1,20,'normal','2025-05-27 12:34:20',11,'反應力',1,NULL),(180,NULL,6,NULL,NULL,'2025-05-27 12:54:58',NULL,'記憶力',1,NULL),(181,NULL,6,NULL,NULL,'2025-05-27 22:52:42',NULL,'記憶力',1,NULL),(182,NULL,6,NULL,NULL,'2025-05-27 22:52:44',NULL,'記憶力',1,NULL),(183,NULL,6,NULL,NULL,'2025-05-27 22:53:39',NULL,'記憶力',1,NULL),(184,NULL,6,NULL,NULL,'2025-05-27 22:54:00',NULL,'記憶力',1,NULL),(185,NULL,6,NULL,NULL,'2025-05-27 22:54:02',NULL,'記憶力',1,NULL),(186,NULL,6,NULL,NULL,'2025-05-27 22:54:04',NULL,'記憶力',1,NULL),(187,NULL,6,NULL,NULL,'2025-05-27 22:54:07',NULL,'記憶力',1,NULL),(188,NULL,6,NULL,NULL,'2025-05-27 22:54:14',NULL,'記憶力',1,NULL),(189,NULL,6,NULL,NULL,'2025-05-27 22:54:22',NULL,'記憶力',1,NULL),(190,NULL,6,NULL,NULL,'2025-05-27 22:59:47',NULL,'記憶力',1,NULL),(191,NULL,6,NULL,NULL,'2025-05-28 14:59:51',NULL,'記憶力',1,NULL),(192,NULL,6,NULL,NULL,'2025-05-28 15:11:32',NULL,'記憶力',1,NULL),(193,NULL,6,NULL,NULL,'2025-05-28 15:12:45',NULL,'記憶力',1,NULL),(194,NULL,6,NULL,NULL,'2025-05-28 15:14:06',NULL,'記憶力',1,NULL),(195,NULL,6,NULL,NULL,'2025-05-28 15:24:12',NULL,'記憶力',1,NULL),(196,NULL,6,NULL,NULL,'2025-05-28 15:33:17',NULL,'記憶力',1,NULL),(197,NULL,6,NULL,NULL,'2025-05-28 16:07:02',NULL,'記憶力',1,NULL),(198,NULL,6,NULL,NULL,'2025-05-28 16:07:04',NULL,'記憶力',1,NULL),(199,NULL,6,NULL,NULL,'2025-05-28 16:11:26',NULL,'記憶力',1,NULL),(200,NULL,6,NULL,NULL,'2025-05-28 16:12:10',NULL,'記憶力',1,NULL),(201,NULL,6,NULL,NULL,'2025-05-28 16:36:09',NULL,'記憶力',1,NULL),(202,NULL,6,NULL,NULL,'2025-05-28 16:36:48',NULL,'記憶力',1,NULL),(203,NULL,6,NULL,NULL,'2025-05-28 16:36:51',NULL,'記憶力',1,NULL),(204,NULL,6,NULL,NULL,'2025-05-28 16:36:54',NULL,'記憶力',1,NULL),(205,NULL,6,NULL,NULL,'2025-05-28 16:38:29',NULL,'記憶力',1,NULL),(206,NULL,6,NULL,NULL,'2025-05-28 16:39:29',NULL,'記憶力',1,NULL),(207,NULL,6,NULL,NULL,'2025-05-28 16:41:51',NULL,'記憶力',1,NULL),(208,NULL,6,NULL,NULL,'2025-05-28 16:43:30',NULL,'記憶力',1,NULL),(209,8,1,60,'easy','2025-07-02 15:50:03',6,'反應力',1,NULL),(210,8,1,60,'normal','2025-07-02 15:50:16',6,'反應力',1,NULL),(211,8,1,40,'hard','2025-07-02 15:50:25',4,'反應力',1,NULL),(212,8,1,40,'easy','2025-07-02 15:54:53',60,'反應力',1,NULL),(213,8,1,30,'easy','2025-07-02 15:56:14',3,'反應力',1,NULL),(214,8,1,0,'easy','2025-07-02 15:57:38',60,'反應力',1,NULL),(215,8,1,40,'easy','2025-07-02 16:01:12',4,'反應力',1,NULL),(216,8,1,50,'easy','2025-07-02 16:01:30',5,'反應力',1,NULL),(217,8,1,10,'easy','2025-07-02 16:03:29',108,'反應力',1,NULL),(218,8,1,6,'easy','2025-07-02 16:03:50',2,'反應力',1,NULL),(219,8,1,12,'normal','2025-07-02 16:04:00',5,'反應力',1,NULL),(225,8,1,6,'easy','2025-07-02 16:32:24',7,'反應力',1,NULL),(226,8,4,1444,'easy','2025-07-02 16:34:56',60,'邏輯力',1,NULL),(229,8,1,24,'easy','2025-07-08 22:25:02',11,'反應力',1,NULL),(230,8,1,0,'easy','2025-07-08 22:49:28',2,'反應力',1,NULL),(231,8,1,0,'normal','2025-07-08 22:49:53',1751986193,'反應力',1,NULL),(310,8,5,0,'easy','2025-07-09 16:06:19',1,'記憶力',1,NULL),(334,8,4,1536,'easy','2025-07-09 16:14:57',60,'邏輯力',1,NULL),(351,8,5,20,'easy','2025-07-09 16:26:27',24,'記憶力',1,NULL),(364,8,4,1344,'easy','2025-07-09 16:35:11',60,'邏輯力',1,NULL),(384,8,4,1516,'easy','2025-07-10 18:20:30',60,'邏輯力',1,NULL),(388,8,6,0,'簡單','2025-07-10 18:21:24',13,'記憶力',1,NULL),(403,8,4,616,'easy','2025-07-10 18:31:56',60,'邏輯力',1,NULL),(409,8,4,1104,'easy','2025-07-10 18:45:55',60,'邏輯力',1,NULL);
/*!40000 ALTER TABLE `game_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `games`
--

DROP TABLE IF EXISTS `games`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `games` (
  `game_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_name` varchar(50) DEFAULT NULL,
  `game_type` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `difficulty_levels` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`game_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `games`
--

LOCK TABLES `games` WRITE;
/*!40000 ALTER TABLE `games` DISABLE KEYS */;
INSERT INTO `games` VALUES (1,'看字選色遊戲','反應力','這是一個訓練反應力和觀察力的遊戲。玩家需要根據文字提示選擇正確的顏色。遊戲分為簡單、普通和困難三種難度，難度越高，顏色選項越多，答題時間越短。\'','easy,normal,hard'),(2,'接金蛋','反應力','接金蛋接金蛋接金蛋接金蛋接金蛋接金蛋接金蛋接金蛋接金蛋接金蛋接金蛋接金蛋接金蛋','easy,normal,hard'),(3,'算菜錢','算數邏輯力','算菜錢算菜錢算菜錢算菜錢算菜錢算菜錢算菜錢算菜錢','easy,normal,hard'),(4,'2048','算數邏輯力','204820482048204820482048204820482048','easy,normal,hard'),(5,'翻牌對對樂','記憶力','翻牌對對樂翻牌對對樂翻牌對對樂翻牌對對樂翻牌對對樂翻牌對對樂翻牌對對樂翻牌對對樂','easy,normal,hard'),(6,'追蹤犯人','記憶力','追蹤犯人追蹤犯人追蹤犯人追蹤犯人追蹤犯人追蹤犯人追蹤犯人追蹤犯人','easy,normal,hard');
/*!40000 ALTER TABLE `games` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `member`
--

DROP TABLE IF EXISTS `member`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `member` (
  `member_id` int(11) NOT NULL AUTO_INCREMENT,
  `member_name` varchar(50) DEFAULT NULL,
  `account` varchar(50) DEFAULT NULL,
  `password` varchar(50) DEFAULT NULL,
  `total_score` int(11) DEFAULT 0,
  `reaction_score` int(11) DEFAULT 0,
  `logic_score` int(11) DEFAULT 0,
  `memory_score` int(11) DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`member_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `member`
--

LOCK TABLES `member` WRITE;
/*!40000 ALTER TABLE `member` DISABLE KEYS */;
INSERT INTO `member` VALUES (6,'黑嚕嚕','black','0501',0,0,0,0,NULL,NULL),(7,'圓仔','圓仔','0501',0,0,0,0,NULL,NULL),(8,'廖婕妤','123','123',1230,230,0,0,NULL,NULL),(10,'Apple','Apple','000',0,0,0,0,NULL,NULL),(11,'Banana','Ban','111',0,0,0,0,NULL,NULL),(12,'Candy','cc','cc',0,0,0,0,NULL,NULL),(19,'恩騏','lin','0717',0,0,0,0,NULL,'img/avatars/avatar_19.png'),(20,'Pandora','Pan','0127',0,0,0,0,NULL,'img/avatars/avatar_20.png'),(21,'婕妤','pp','0127',0,0,0,0,NULL,'img/avatars/avatar_21.png'),(22,'ouu','ouu','ouu',0,0,0,0,NULL,NULL);
/*!40000 ALTER TABLE `member` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `member_tasks`
--

DROP TABLE IF EXISTS `member_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `member_tasks` (
  `record_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '紀錄編號',
  `member_id` int(11) NOT NULL COMMENT '會員編號',
  `task_id` int(11) NOT NULL COMMENT '任務編號',
  `completed_date` datetime DEFAULT NULL COMMENT '完成日期',
  `status` varchar(20) DEFAULT 'pending' COMMENT '完成狀態: completed/failed/pending',
  PRIMARY KEY (`record_id`),
  KEY `member_id` (`member_id`),
  KEY `task_id` (`task_id`),
  CONSTRAINT `member_tasks_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `member` (`member_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `member_tasks_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `daily_tasks` (`task_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COMMENT='玩家任務記錄表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `member_tasks`
--

LOCK TABLES `member_tasks` WRITE;
/*!40000 ALTER TABLE `member_tasks` DISABLE KEYS */;
INSERT INTO `member_tasks` VALUES (3,8,52,'2025-07-14 02:51:46','completed');
/*!40000 ALTER TABLE `member_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `memory_game_colors`
--

DROP TABLE IF EXISTS `memory_game_colors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `memory_game_colors` (
  `color_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL,
  `color_name` varchar(50) NOT NULL,
  `color_chinese` varchar(20) NOT NULL,
  `color_code` varchar(20) NOT NULL,
  `difficulty_level` varchar(10) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`color_id`),
  KEY `fk_colors_gameid_new` (`game_id`),
  CONSTRAINT `fk_colors_gameid` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_colors_gameid_new` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `memory_game_colors`
--

LOCK TABLES `memory_game_colors` WRITE;
/*!40000 ALTER TABLE `memory_game_colors` DISABLE KEYS */;
INSERT INTO `memory_game_colors` VALUES (1,1,'cardBack','卡片背面','#FFB6C1','easy',1),(2,1,'cardFront','卡片正面','#FF69B4','easy',1),(3,1,'matched','配對成功','#FFC0CB','easy',1),(4,1,'background','背景','#FFF0F5','easy',1),(5,1,'container','容器','white','easy',1);
/*!40000 ALTER TABLE `memory_game_colors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `memory_game_difficulty_settings`
--

DROP TABLE IF EXISTS `memory_game_difficulty_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `memory_game_difficulty_settings` (
  `difficulty_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL,
  `difficulty_level` varchar(20) NOT NULL,
  `color_count` int(11) NOT NULL,
  `time_limit` int(11) NOT NULL,
  `score_multiplier` decimal(5,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`difficulty_id`),
  KEY `fk_difficulty_gameid` (`game_id`),
  CONSTRAINT `fk_difficulty_gameid` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `memory_game_difficulty_settings`
--

LOCK TABLES `memory_game_difficulty_settings` WRITE;
/*!40000 ALTER TABLE `memory_game_difficulty_settings` DISABLE KEYS */;
INSERT INTO `memory_game_difficulty_settings` VALUES (1,1,'easy',6,60,1.00,1),(2,1,'normal',8,120,1.50,1),(3,1,'hard',16,180,2.00,1);
/*!40000 ALTER TABLE `memory_game_difficulty_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `memory_game_images`
--

DROP TABLE IF EXISTS `memory_game_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `memory_game_images` (
  `image_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`image_id`),
  KEY `fk_images_gameid` (`game_id`),
  CONSTRAINT `fk_images_gameid` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `memory_game_images`
--

LOCK TABLES `memory_game_images` WRITE;
/*!40000 ALTER TABLE `memory_game_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `memory_game_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `memory_game_themes`
--

DROP TABLE IF EXISTS `memory_game_themes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `memory_game_themes` (
  `theme_id` int(11) NOT NULL AUTO_INCREMENT,
  `theme_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `theme_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `theme_style` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`theme_style`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`theme_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `memory_game_themes`
--

LOCK TABLES `memory_game_themes` WRITE;
/*!40000 ALTER TABLE `memory_game_themes` DISABLE KEYS */;
INSERT INTO `memory_game_themes` VALUES (1,'fruit','水果主題','{\"icon\": \"🍎\", \"cardBack\": \"#FFB6C1\", \"cardFront\": \"#FF69B4\", \"matched\": \"#FFC0CB\", \"background\": \"#FFF0F5\", \"container\": \"white\"}',1),(2,'animal','動物主題','{\"icon\": \"🐶\", \"cardBack\": \"#90CAF9\", \"cardFront\": \"#2196F3\", \"matched\": \"#4CD964\", \"background\": \"#E3F2FD\", \"container\": \"white\"}',1),(3,'daily','日常用品主題','{\"icon\": \"⌚\", \"cardBack\": \"#B39DDB\", \"cardFront\": \"#673AB7\", \"matched\": \"#9575CD\", \"background\": \"#EDE7F6\", \"container\": \"white\"}',1),(4,'vegetable','蔬菜主題','{\"icon\": \"🥬\", \"cardBack\": \"#81C784\", \"cardFront\": \"#4CAF50\", \"matched\": \"#66BB6A\", \"background\": \"#E8F5E9\", \"container\": \"white\"}',1);
/*!40000 ALTER TABLE `memory_game_themes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prisoner_game_difficulty_settings`
--

DROP TABLE IF EXISTS `prisoner_game_difficulty_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `prisoner_game_difficulty_settings` (
  `difficulty_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL COMMENT '對應的遊戲 ID',
  `difficulty_level` varchar(20) NOT NULL COMMENT '難度等級，如簡單、普通、困難',
  `countdown_seconds` int(11) NOT NULL COMMENT '該難度的倒數時間（秒）',
  `score_multiplier` int(11) NOT NULL COMMENT '得分倍率',
  `is_active` tinyint(1) NOT NULL,
  PRIMARY KEY (`difficulty_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prisoner_game_difficulty_settings`
--

LOCK TABLES `prisoner_game_difficulty_settings` WRITE;
/*!40000 ALTER TABLE `prisoner_game_difficulty_settings` DISABLE KEYS */;
INSERT INTO `prisoner_game_difficulty_settings` VALUES (1,6,'easy',60,1,1),(2,6,'normal',60,1,1),(3,6,'hard',120,1,1);
/*!40000 ALTER TABLE `prisoner_game_difficulty_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `questions`
--

DROP TABLE IF EXISTS `questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `questions` (
  `question_id` int(11) NOT NULL AUTO_INCREMENT,
  `image_path` varchar(255) NOT NULL,
  `question_text` text NOT NULL,
  `option_1` text NOT NULL,
  `option_2` text NOT NULL,
  `option_3` text NOT NULL,
  `option_4` text NOT NULL,
  `correct_answer_text` text NOT NULL,
  `display_time` int(11) DEFAULT 10,
  `difficulty` enum('簡單','普通','困難') DEFAULT NULL,
  PRIMARY KEY (`question_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `questions`
--

LOCK TABLES `questions` WRITE;
/*!40000 ALTER TABLE `questions` DISABLE KEYS */;
INSERT INTO `questions` VALUES (1,'two people-1.jpg','請問左邊的女生穿什麼顏色的衣服?\r\n','紅色','黃色','藍色','綠色','黃色',10,'簡單'),(2,'two people-1.jpg','請問右邊的女生穿什麼顏色的衣服?\r\n','紅色','黃色','藍色','綠色','藍色',10,'簡單');
/*!40000 ALTER TABLE `questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rhythm_game_difficulty_settings`
--

DROP TABLE IF EXISTS `rhythm_game_difficulty_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rhythm_game_difficulty_settings` (
  `difficulty_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL COMMENT '對應哪個遊戲',
  `difficulty_level` varchar(20) NOT NULL COMMENT ' easy / normal / hard',
  `countdown_seconds` int(11) NOT NULL COMMENT '倒數時間',
  `score_multiplier` float NOT NULL COMMENT '得分倍率',
  `is_active` tinyint(1) NOT NULL,
  PRIMARY KEY (`difficulty_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rhythm_game_difficulty_settings`
--

LOCK TABLES `rhythm_game_difficulty_settings` WRITE;
/*!40000 ALTER TABLE `rhythm_game_difficulty_settings` DISABLE KEYS */;
INSERT INTO `rhythm_game_difficulty_settings` VALUES (1,7,'easy',60,1,1),(2,7,'normal',60,1,1),(3,7,'hard',60,1,1);
/*!40000 ALTER TABLE `rhythm_game_difficulty_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `text_color_colors`
--

DROP TABLE IF EXISTS `text_color_colors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `text_color_colors` (
  `color_id` int(11) NOT NULL AUTO_INCREMENT,
  `color_name` varchar(20) DEFAULT NULL,
  `color_name_chinese` varchar(20) DEFAULT NULL,
  `color_code` varchar(7) DEFAULT NULL,
  PRIMARY KEY (`color_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `text_color_colors`
--

LOCK TABLES `text_color_colors` WRITE;
/*!40000 ALTER TABLE `text_color_colors` DISABLE KEYS */;
INSERT INTO `text_color_colors` VALUES (1,'red','紅色','#FF0000'),(2,'blue','藍色','#0000FF'),(3,'green','綠色','#00FF00'),(4,'yellow','黃色','#FFFF00'),(5,'purple','紫色','#800080'),(6,'orange','橙色','#FFA500'),(7,'pink','粉色','#FFC0CB'),(8,'brown','棕色','#A52A2A'),(9,'gray','灰色','#808080'),(10,'black','黑色','#000000');
/*!40000 ALTER TABLE `text_color_colors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `text_color_difficulty_settings`
--

DROP TABLE IF EXISTS `text_color_difficulty_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `text_color_difficulty_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `difficulty` varchar(10) DEFAULT NULL,
  `color_count` int(11) DEFAULT NULL,
  `question_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`setting_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `text_color_difficulty_settings`
--

LOCK TABLES `text_color_difficulty_settings` WRITE;
/*!40000 ALTER TABLE `text_color_difficulty_settings` DISABLE KEYS */;
INSERT INTO `text_color_difficulty_settings` VALUES (1,'easy',3,0),(2,'normal',6,3),(3,'hard',9,5);
/*!40000 ALTER TABLE `text_color_difficulty_settings` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-07-16 20:21:37
