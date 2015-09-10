DROP PROCEDURE IF EXISTS sp_show_grants;

DELIMITER ;;

CREATE PROCEDURE sp_show_grants()
    READS SQL DATA
    COMMENT 'Show GRANT statements for users'
BEGIN
    DECLARE v VARCHAR(64) CHARACTER SET utf8;
    DECLARE c CURSOR FOR
    SELECT DISTINCT CONCAT(
        'SHOW GRANTS FOR ', user, "@'", host, "';"
    ) AS query FROM mysql.user;
    DECLARE EXIT HANDLER FOR NOT FOUND BEGIN END;  
    OPEN c;
    WHILE TRUE DO
        FETCH c INTO v;
        SET @v = v;
        PREPARE stmt FROM @v;
        EXECUTE stmt;
    END WHILE;
    CLOSE c;
END

;;
DELIMITER ;
