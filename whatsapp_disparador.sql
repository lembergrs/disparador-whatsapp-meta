# SQL-Front 5.1  (Build 4.16)

/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE */;
/*!40101 SET SQL_MODE='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES */;
/*!40103 SET SQL_NOTES='ON' */;


# Host: localhost:3307    Database: whatsapp_disparador
# ------------------------------------------------------
# Server version 5.5.5-10.6.3-MariaDB

#
# Source for table clientes
#

CREATE TABLE `clientes` (
  `CLI_ID` int(11) NOT NULL AUTO_INCREMENT,
  `CLI_Nome` varchar(200) NOT NULL,
  `CLI_Ativo` char(1) DEFAULT 'S',
  `CLI_DataCadastro` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`CLI_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

#
# Dumping data for table clientes
#


#
# Source for table contatos
#

CREATE TABLE `contatos` (
  `CON_ID` int(11) NOT NULL AUTO_INCREMENT,
  `CLI_ID` int(11) NOT NULL,
  `CON_Nome` varchar(200) DEFAULT NULL,
  `CON_Telefone` varchar(30) DEFAULT NULL,
  `CON_DataImportacao` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`CON_ID`),
  KEY `CLI_ID` (`CLI_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

#
# Dumping data for table contatos
#


#
# Source for table usuarios
#

CREATE TABLE `usuarios` (
  `USU_ID` int(11) NOT NULL AUTO_INCREMENT,
  `CLI_ID` int(11) NOT NULL,
  `USU_Nome` varchar(150) NOT NULL,
  `USU_Email` varchar(150) NOT NULL,
  `USU_Senha` varchar(255) NOT NULL,
  `USU_Ativo` char(1) DEFAULT 'S',
  PRIMARY KEY (`USU_ID`),
  UNIQUE KEY `USU_Email` (`USU_Email`),
  KEY `CLI_ID` (`CLI_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

#
# Dumping data for table usuarios
#

INSERT INTO `usuarios` VALUES (2,1,'Administrador','admin@admin.com','$2y$10$E7iL3m30J3XCPFoHq69wM.6JeZZkhEQEv6o5vsNm4rv3Qz19UuR.S','S');

/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
