# GitUpdater - WordPress Auto-Deploy

WordPress plugin om plugins en thema's automatisch te installeren en bij te werken vanuit GitHub repositories via push-to-deploy.

## Functionaliteiten

- **Push-to-Deploy** — automatische updates bij elke push naar GitHub of nieuwe release
- **Webhook integratie** — real-time updates via GitHub webhooks
- **Complete lifecycle** — installeren, activeren, updaten en verwijderen vanuit de admin interface
- **Repository scanner** — automatisch je GitHub repos scannen en filteren op WordPress-gerelateerde projecten
- **HMAC SHA256 verificatie** — beveiligde webhook authenticatie
- **Rate limiting** — maximaal 10 webhooks per minuut
- **ZIP bomb protection** — 100MB download limiet

## Vereisten

- WordPress 5.0 of hoger
- PHP 7.4 of hoger
- GitHub Personal Access Token met `repo` rechten

## Installatie

1. Upload de plugin naar `/wp-content/plugins/github-push-to-deploy/`
2. Activeer de plugin via het 'Plugins' menu in WordPress
3. Ga naar **GitHub Deploy** in het WordPress admin menu

## Snelle start

### 1. GitHub Token instellen
1. Ga naar [GitHub Settings > Personal Access Tokens](https://github.com/settings/tokens)
2. Genereer een nieuw token met `repo` rechten
3. Plak het token in de plugin instellingen

### 2. Repository toevoegen
1. Klik op "Repository toevoegen"
2. Voer je GitHub URL in (bijv. `username/mijn-plugin`)
3. Selecteer type: Plugin of Thema
4. Klik op "Repository Toevoegen"

### 3. Installeren en activeren
1. Klik op "Installeren" bij je repository
2. Webhook wordt automatisch ingesteld
3. Klik op "Activeren" om de plugin te activeren

## Webhook URL

```
https://jouwwebsite.com/wp-json/gh-deployer/v1/webhook
```

Ondersteunde events: Push, Release en Ping.

## Beveiliging

- HMAC SHA256 verificatie voor alle webhook requests
- Token-gebaseerde authenticatie voor GitHub API calls
- Rate limiting (10 webhooks/minuut)
- ZIP bomb protection (100MB limiet)
- File type validation en path traversal prevention
- WP_Filesystem API voor veilige bestandsoperaties
- Capability checks (`manage_options`)

## Licentie

GPLv2 or later — zie [LICENSE](LICENSE).

## Changelog

Zie het volledige [CHANGELOG](CHANGELOG) voor alle versies.
