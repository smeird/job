# job

## Data retention

A retention policy can be managed from the `/retention` settings page once signed in. The policy controls how many days data is retained and which data sets are purged.

### Daily purge cron

Run the purge script every day after hours to hard-delete rows that fall outside the retention window:

```
0 2 * * * /usr/bin/php /var/www/job/bin/purge.php >> /var/log/job/purge.log 2>&1
```

Adjust the PHP binary path, project directory, and log destination to match your environment.
