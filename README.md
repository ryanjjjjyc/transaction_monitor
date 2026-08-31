# Transaction Monitor
The transaction Monitor is implemented in Windows

## How to run
1. First run:
```
composer install
```
2. Create the sqlite database using the seed.php file by running (Termminal 1: Powershell):
```
php .\seed.php    
```
3. Start the local host using (Terminal 1: Powershell):
```
php -S localhost:8080 -t public
```

4. Start the worker in the background (Terminal 2: Powershell):
```
php -S localhost:8080 -t public
```
5. The following transaction are all run in Terminal 3 Command Prompt: 

    1.  Create a transaction:
    ```
    curl.exe -X POST http://localhost:8080/transactions -H "Content-Type: application/json" -H "X-API-Key: key_alice" -d "{\"to_user_id\": 2, \"amount\": 150}"
    ```

    2. Send a very large amount or multiple rapid requests (within 60 seconds) to trigger the rule engine:
    ```
    curl.exe -X POST http://localhost:8080/transactions -H "Content-Type: application/json" -H "X-API-Key: key_alice" -d "{\"to_user_id\": 2, \"amount\": 12000}"
    ```
    3. List user’s transactions:
    ```
    curl.exe -H "X-API-Key: key_alice" http://localhost:8080/transactions
    ```
    4. Get a specific transaction:
    ```
    curl.exe -H "X-API-Key: key_alice" http://localhost:8080/transactions/1
    ```
    5. Label a transaction (ground truth):
    ```
    curl.exe -X POST http://localhost:8080/transactions/1/label -H "Content-Type: application/json" -H "X-API-Key: key_alice" -d "{\"label\": 1}"
    ```
    6. List flagged transactions:
    ```
    curl.exe -H "X-API-Key: key_alice" http://localhost:8080/monitoring/flags
    ```
    7. To check balance:
    ```
    sqlite3 db\database.sqlite
    SELECT id, username, balance FROM users;
    ```
