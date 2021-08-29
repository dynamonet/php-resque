# php-resque

Resque on steroids. Based on Chris Boulton's work, but with a powerful Symfony-based CLI with which you can:

- List running workers and jobs... all of them (even if they´re distributed on other machines)
- Pause, resume or cancel running jobs (yes, even if they are running on a remote worker)

# CLI reference

Our CLI is based on [Symfony's Console component](https://symfony.com/doc/current/components/console.html). To see all available command, simply run:

```bash
php resque
```

## Push/Enqueue a job

Pushes a job to your resque queues.

```bash
php resque job:push job_type args
```
