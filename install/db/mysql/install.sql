CREATE TABLE IF NOT EXISTS ps_emulator1c_log (
    ID int(11) NOT NULL AUTO_INCREMENT,
    DATE_CREATE datetime NOT NULL,
    FILE_NAME varchar(255) NOT NULL,
    STATUS varchar(50) NOT NULL,
    MESSAGE text,
    PRIMARY KEY (ID)
); 