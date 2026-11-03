# Contributing

Thanks for helping improve Bachs Donations. Keep changes focused, documented, and compatible with the supported WordPress and PHP versions in the plugin header.

## Development workflow

1. Fork or clone the repository and create a focused branch.
2. Make changes in `includes/` by responsibility; keep Bachs API calls behind `ApiClient` and never pass card data through WordPress.
3. Update `README.md` and `readme.txt` when behavior, settings, security, or installation steps change.
4. Run PHP syntax checks on every PHP file:

   ```sh
   find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
   ```

5. Exercise payment changes on a disposable WordPress site with Bachs Sandbox credentials. Do not use real donor funds for development.
6. Open a pull request with a summary, steps to verify, and any limitations or migration notes.

## Security and privacy

- Never commit API keys, webhook signing secrets, donor details, WordPress configuration, or live payment payloads.
- Verify webhooks against the exact raw request body and keep amount and currency checks server-side.
- Avoid logging credentials, full webhook bodies, or donor personal information.
- Report suspected vulnerabilities privately as described in [SECURITY.md](SECURITY.md).
