=== Auto-Deploy for GitHub ===
Contributors: RikkerMediaHub
Tags: github, deployment, auto-update, webhook, plugins
Requires at least: 5.0
Tested up to: 6.8.3
Stable tag: 2.4.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatisch WordPress plugins en thema's installeren/bijwerken van GitHub repository's via push-to-deploy.

## Wat deze plugin doet

Deze plugin maakt het mogelijk om WordPress plugins en thema's automatisch te installeren en bij te werken vanuit GitHub repositories. Zodra je code pusht naar GitHub of een nieuwe release maakt, wordt je plugin of thema automatisch bijgewerkt op je WordPress website.

## Hoofdfunctionaliteiten

### 🚀 **Automatische Deployment**
- **Push-to-Deploy**: Automatische updates bij elke push naar GitHub
- **Release Deployment**: Automatische installatie bij nieuwe GitHub releases
- **Real-time Updates**: Webhook integratie voor directe updates
- **Handmatige Installatie**: Eenvoudige installatie via admin interface

### 🔧 **Complete Plugin/Thema Lifecycle**
- **Installeren**: Download en installeer plugins/thema's vanuit GitHub
- **Activeren**: Direct activeren van plugins vanuit de interface
- **Updaten**: Automatische updates bij nieuwe commits/releases
- **Verwijderen**: Volledige verwijdering van plugins/thema's en gerelateerde data

### 🛡️ **Geavanceerde Beveiliging**
- **HMAC SHA256 Verificatie**: Beveiligde webhook authenticatie
- **Rate Limiting**: Maximaal 10 webhooks per minuut
- **ZIP Bomb Protection**: 100MB download limiet
- **File Type Validation**: Blokkeert gevaarlijke bestandstypen
- **Input Sanitization**: Alle gebruikersinvoer wordt gesanitiseerd

### 🎯 **Slimme Repository Detectie**
- **Auto-detectie**: Automatische detectie van WordPress slug
- **GitHub Browse**: Blader door je GitHub repositories
- **WordPress Filtering**: Toont alleen repositories die WordPress plugins/thema's kunnen zijn
- **Smart Slug Generation**: Intelligente conversie van repository namen naar WordPress slugs

### ⚙️ **Eenvoudige Configuratie**
- **GitHub Token Integratie**: Eenvoudige GitHub API authenticatie
- **Automatische Webhook Setup**: Webhooks worden automatisch ingesteld
- **Visual Status Indicators**: Duidelijke status badges voor installatie en webhook status
- **Responsive Interface**: Moderne, gebruiksvriendelijke admin interface

## Installatie

1. Upload de plugin naar `/wp-content/plugins/github-push-to-deploy/`
2. Activeer de plugin via het 'Plugins' menu in WordPress
3. Ga naar **GitHub Deploy** in het WordPress admin menu

## Snelle Start

### Stap 1: GitHub Token Instellen
1. Ga naar [GitHub Settings > Personal Access Tokens](https://github.com/settings/tokens)
2. Genereer een nieuw token met `repo` rechten
3. Plak het token in de plugin instellingen

### Stap 2: Repository Toevoegen
1. Klik op "Repository toevoegen" in de plugin interface
2. Voer je GitHub URL in (bijv. `username/mijn-plugin`)
3. Selecteer type: Plugin of Thema
4. Klik op "Repository Toevoegen"

### Stap 3: Installeren & Activeren
1. Klik op "Installeren" bij je repository
2. De plugin wordt automatisch geïnstalleerd
3. Webhook wordt automatisch ingesteld
4. Klik op "Activeren" om de plugin te activeren

## Geavanceerde Functionaliteiten

### 🔍 **GitHub Repository Scanner**
- Automatisch scannen van je GitHub repositories
- Filtering op WordPress-gerelateerde repositories
- Bulk toevoegen van meerdere repositories tegelijk

### 📊 **Status Monitoring**
- Real-time status van alle repositories
- Webhook status monitoring
- Installatie en activatie status
- Versie informatie (huidig vs. beschikbaar)

### 🔄 **Automatische Webhook Beheer**
- Automatische webhook creatie bij installatie
- Webhook status monitoring
- Eenvoudige webhook activatie/deactivatie
- Geen handmatige GitHub configuratie nodig

### 🛠️ **Developer Features**
- Uitgebreide logging voor debugging
- REST API endpoints voor integratie
- AJAX-powered interface voor snelle updates
- WordPress.org compliant code

## Webhook URL

```
https://jouwwebsite.com/wp-json/gh-deployer/v1/webhook
```

## Ondersteunde Events

- **Push Events**: Automatische updates bij code pushes
- **Release Events**: Updates bij nieuwe releases  
- **Ping Events**: Webhook test functionaliteit

## Beveiligingsmaatregelen

- **HMAC SHA256 verificatie** voor alle webhook requests
- **Token-gebaseerde authenticatie** voor GitHub API calls
- **Rate limiting** - Maximaal 10 webhooks per minuut
- **ZIP bomb protection** - 100MB download limiet
- **File type validation** - Gevaarlijke bestandsextensies geblokkeerd
- **Path traversal prevention** - Beveiliging tegen directory traversal aanvallen
- **WP_Filesystem API** - Veilige bestandsoperaties
- **Capability checks** - Alleen gebruikers met `manage_options` rechten

## Logging & Debugging

De plugin logt alle activiteiten naar de WordPress debug log:

- Webhook ontvangst en verwerking
- Download en installatie status  
- Plugin activatie en lifecycle events
- Rate limiting en beveiligingsmeldingen
- Foutmeldingen en debugging informatie

**Log locatie:** `/wp-content/debug.log` (als WP_DEBUG_LOG is ingeschakeld)

## Troubleshooting

### Webhook werkt niet
1. Controleer of de webhook URL correct is
2. Verificeer dat de webhook secret overeenkomt
3. Controleer de WordPress error logs
4. Test de GitHub verbinding in de plugin instellingen

### Plugin/Thema wordt niet bijgewerkt
1. Controleer of de repository correct is geconfigureerd
2. Verificeer dat de GitHub token de juiste rechten heeft
3. Controleer of de WordPress slug correct is ingesteld
4. Bekijk de error logs voor specifieke foutmeldingen

### Permission errors
1. Controleer of WordPress schrijfrechten heeft op `/wp-content/plugins/` en `/wp-content/themes/`
2. Controleer server file permissions
3. Bekijk de WordPress debug logs

### Plugin activatie werkt niet
1. Controleer of de plugin correct is geïnstalleerd
2. Verificeer dat de WordPress slug correct is ingesteld
3. Controleer of de plugin file bestaat in de plugins directory
4. Bekijk de browser console voor JavaScript errors

## Ondersteuning

Voor vragen en ondersteuning:
- Controleer de WordPress error logs
- Bekijk de GitHub repository voor updates
- Maak een issue aan in de GitHub repository

## Changelog

### 2.4.5 (07-10-2025)
- 🔧 **Plugin Check compliance** - README.md tags beperkt tot 5 (max toegestaan)
- 📝 **WordPress compatibility** bijgewerkt naar versie 6.8
- 🏷️ **Tag optimalisatie** - 'themes' tag verwijderd om binnen limiet te blijven

### 2.4.4 (07-10-2025)
- 🔒 **Beveiligingsverbetering** - InputNotSanitized warnings opgelost
- 🛡️ **Array sanitization** - Alle POST repositories en items nu correct gesanitiseerd
- ✅ **WordPress.org compliance** - Verbeterde input validatie en beveiliging

### 2.4.3 (07-10-2025)
- 📁 **File validation fix** - Legitieme WordPress bestanden nu toegestaan
- 🔍 **Verbeterde bestandsvalidatie** voor betere compatibiliteit

### 2.4.2 (07-10-2025)
- 🚨 **CRITICAL FIX** - recursive_rmdir() fatal error opgelost
- 🔧 **WP_Filesystem fix** - Terug naar betrouwbare PHP rmdir() implementatie
- ⚡ **Stabiliteit** - Geen fatal errors meer bij webhook of handmatige installatie

### 2.4.1 (07-10-2025)
- ✅ **FINAL Plugin Check compliance** - Alle issues opgelost
- 🗑️ **Cleanup** - .gitkeep bestand verwijderd (niet toegestaan)
- 📝 **Logging verbetering** - error_log() vervangen door wp_debug_log()
- 🔒 **Beveiliging** - Enhanced input validation voor alle _POST variabelen

### 2.4.0 (07-10-2025)
- 🎯 **PRODUCTION READY** - Complete Plugin Check compliance
- 🌐 **WordPress.org submission ready** - Alle standaarden voldaan
- 🔐 **Security review** - Volledige beveiligingsaudit doorstaan
- 📁 **Languages folder** - Domain Path header compliance
- 🛡️ **Rate limiting** - 10 webhooks per minuut limiet
- 💣 **ZIP bomb protection** - 100MB limiet voor downloads

### 2.3.8 (07-10-2025)
- ✨ **Plugin activatie functionaliteit** - Eenvoudige activatie vanuit interface
- 🎯 **Smart button logic** - Dynamische knoppen per plugin status
- 🎨 **UI/UX verbetering** - Nieuwe btn-success styling en feedback
- 🔄 **Complete plugin lifecycle** - Installeren, activeren en updaten

### 2.3.7 (07-10-2025)
- 🗑️ **Complete removal** - Plugins en thema's volledig verwijderen bij repository delete
- 🧹 **Database cleanup** - Alle gerelateerde data wordt opgeruimd

### 2.3.6 (07-10-2025)
- 🎨 **Hover effects** - Verbeterde repository cards styling
- 📱 **Responsive design** - Optimalisatie voor laptops en desktops
- 🔧 **CSS/JS loading** - Fix na menu verplaatsing

### 2.0.1
- **Test versie** voor webhook automatisering
- **Verbeterde webhook management** interface
- **Bug fixes** en stabiliteit verbeteringen

### 2.0.0
- **Nieuwe functionaliteit:** Handmatige installatie knoppen
- **Verbeterde GitHub API authenticatie** (Bearer token support)
- **Betere error handling** met specifieke foutmeldingen
- **Verbeterde ZIP verwerking** voor GitHub repository's
- **Uitgebreide logging** voor betere debugging
- **Responsive admin interface** met verbeterde UX

### 1.0.0
- Eerste release
- Webhook integratie met GitHub
- Automatische plugin/thema installatie
- Admin interface voor configuratie
- Beveiligde webhook verificatie