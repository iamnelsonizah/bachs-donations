# Security policy

Use the latest release when possible. Security updates will be documented in the repository's release notes.

Please do not report a suspected vulnerability in a public issue. Use GitHub's **Report a vulnerability** option in the repository Security tab when private vulnerability reporting is available. If that option is unavailable, contact the repository maintainer through their GitHub profile and include only the details needed to reproduce the issue. Do not include real API keys, donor data, or production webhook payloads.

This project handles payment status and donor contact details. Deploy it over HTTPS, use restricted Bachs API keys, configure signed webhooks, and test changes in Sandbox before going live.
