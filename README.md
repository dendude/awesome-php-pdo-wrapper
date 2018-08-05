# PDO wrapper for really easy queries to the database

Useful wrapper for PDO using >=php7.1

Database\DB class realizes a singleton/multiton desing pattern.

### Table example

```mysql
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `role` varchar(50) NOT NULL,
  `name` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `visits` int(10) unsigned NOT NULL DEFAULT 0,
  `created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

INSERT INTO `users` (`id`, `role`, `name`, `email`) VALUES
(1, 'admin', 'John Smith', 'js@gmail.com'),
(2, 'user', 'Barbara Johnson', 'bj@gmail.com'),
(3, 'manager', 'Mary Lee', 'mee@gmail.com'),
(4, 'user', 'Lucia Woods', 'uw@gmail.com'),
(5, 'user', 'Brandon Alister', 'ba@gmail.com');
```

### Get instance
```php
use Database\DB;
use Database\DBExpression;

$db = DB::getInstanse(); // default db
$db2 = DB::getInstanse(DB::CONFIG_OTHER); // another db 
```

### Select
```php
// simple select
$rows = $db->selectAll('users', ['role' => 'admin']);
```
```php
// select with NULL option
$rows = $db->selectAll('users', [
    'role' => 'admin', 
    'created' => 'IS NOT NULL' // or 'created' => 'IS NULL'
]);
```

### Select row
```php
$row = $db->selectRow('users', ['email' => 'admin@gmail.com']);
```

### Select row by id
```php
$row = $db->selectRowById('users', 3);
```

### Select column
```php
$emails = $db->selectColumn('users', 'email');
```

### Select cell
```php
$email = $db->selectCell('users', 'email', ['id' => 3]);
```

### Select count
```php
$count = $db->selectCount('users');
```

### Insert data
```php
// simple insert
$id = $db->insert('users', ['role' => 'user', 'name' => 'new name', 'email' => 'new@gmail.com']);
```
```php
// insert with mysql expression
$id = $db->insert('users', [
    'role' => 'user', 
    'name' => 'new name', 
    'email' => 'new@gmail.com', 
    'created' => new DBExpression('NULL') // or 'created' => null
]);
```

### Update data
```php
// simple update
$affected = $db->update('users', ['role' => 'manager'], ['id' => 2]);
```
```php
// update with mysql expression
$affected = $db->update('users', [
    'role' => 'manager', 
    'created' => new DBExpression('NOW()')
], ['id' => 2]);
```

### Update counter fields
```php
$affected = $db->updateCounters('users', ['visits' => 1], ['id' => 3]);
```

### Check existing record
```php
$isExist = $db->exists('users', ['role' => 'admin']);
```

### Delete rows
```php
$db->delete('users', ['visits' => 0]);
```

### Close connections of the all instances
```php
DB::closeConections();
```
