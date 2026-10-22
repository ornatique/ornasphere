# GST search and customer prefill

Open Persons List → Search GSTIN / Add Customer. Search, review the result, and click Add Customer. The existing customer form opens with name, GSTIN, PAN and available address fields. Customer category is selected when that category exists. Review the form and save normally. No record is created by searching.

Provider documentation: https://www.gstinapi.in/docs

Create a provider account and set `GSTIN_API_KEY` in the server `.env`. Never put the key in JavaScript or commit it. Run `php artisan config:clear` after configuring it (or rebuild the deployment's configuration cache).

The provider currently advertises 25 signup credits and up to 100 through setup steps. These are finite free credits, not an unlimited free service. No account purchase, recharge, or paid subscription is performed by this application. A lookup consumes provider credits. Use a free-credit-only account/key if paid usage is not wanted.

Search requires the same create-person permission as opening the customer form, is company-scoped, CSRF-protected and throttled. The API key stays server-side. Missing keys, exhausted credits, missing GSTINs and service failures show separate messages. Results are kept in the user's session for 30 minutes, with a company-scoped random token used to open the prefilled form. The next successful lookup replaces the previous draft. Existing company GSTINs are flagged instead of offering another Add Customer button.

Fields unavailable from the provider are left blank. Mobile and email are never guessed. City is filled only from the structured city field, not inferred from an address locality. GST registration status is displayed for review; it does not silently change the application's customer active/inactive setting.

Verification: `php vendor/phpunit/phpunit/phpunit --filter GstinLookupTest`
Live verification requires a configured key; automated tests fake provider responses and consume no credits.
