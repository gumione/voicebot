# VOICEBOT

## Installation

 1. `cd your_app_dir && git clone https://github.com/gumione/voicebot.git . `
 2. `composer install`
 3. Add your TG bot credentials into the `config/services.yaml` file (do not forget to allow /inline mode for bot so it can be mentioned using @botname)
 4. Put your DB connection settings into the `.env` file
 5. Add `LEVENSHTEIN` function to your MySQL DB:
 ```
 DELIMITER $$
 BEGIN
  DECLARE s1_len, s2_len, i, j, c, c_temp, cost INT;
  DECLARE s1_char CHAR(1) CHARACTER SET utf8;
  DECLARE cv0, cv1 VARBINARY(256);
  SET s1_len = CHAR_LENGTH(s1), s2_len = CHAR_LENGTH(s2), cv1 = 0x00, j = 1, i = 1, c = 0;
  IF s1 = s2 THEN
    RETURN 0;
  ELSEIF s1_len = 0 THEN
    RETURN s2_len;
  ELSEIF s2_len = 0 THEN
    RETURN s1_len;
  ELSE
    WHILE j <= s2_len DO
      SET cv1 = CONCAT(cv1, UNHEX(HEX(j))), j = j + 1;
    END WHILE;
    WHILE i <= s1_len DO
      SET s1_char = SUBSTRING(s1, i, 1), c = i, cv0 = UNHEX(HEX(c)), j = 1;
      WHILE j <= s2_len DO
        SET c = c + 1;
        IF s1_char = SUBSTRING(s2, j, 1) THEN 
          SET cost = 0; ELSE SET cost = 1;
        END IF;
        SET c_temp = CONV(HEX(SUBSTRING(cv1, j, 1)), 16, 10) + cost;
        IF c > c_temp THEN 
          SET c = c_temp; 
        END IF;
        SET c_temp = CONV(HEX(SUBSTRING(cv1, j + 1, 1)), 16, 10) + 1;
        IF c > c_temp THEN
          SET c = c_temp;
        END IF;
        SET cv0 = CONCAT(cv0, UNHEX(HEX(c))), j = j + 1;
      END WHILE;
      SET cv1 = cv0, i = i + 1;
    END WHILE;
  END IF;
  RETURN c;
END$$ 
 DELIMITER ;
 ```
 6. Put your audiofiles into the `public/audio` directory
 7. `php bin/console  app:import-audio` will scan the dir and add audio files to DB
 8. Enjoy!

## Live example

> http://t.me/gtalks_bot