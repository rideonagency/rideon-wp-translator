# RideOn WP Translator - Documentazione Completa

## Indice

1. [Panoramica](#panoramica)
2. [Installazione](#installazione)
3. [Configurazione](#configurazione)
4. [Utilizzo](#utilizzo)
5. [Architettura](#architettura)
6. [API e Hook](#api-e-hook)
7. [Sviluppo](#sviluppo)
8. [Troubleshooting](#troubleshooting)

---

## Panoramica

RideOn WP Translator è un plugin WordPress che utilizza l'API di OpenAI per tradurre automaticamente i contenuti del sito. Il plugin traduce titolo, contenuto ed excerpt mantenendo metadati come categorie, tag e immagine in evidenza.

### Caratteristiche Principali

- **Traduzione Automatica**: Traduzione con un singolo click dal metabox nell'editor dei post
- **Integrazione OpenAI**: Utilizza modelli GPT-3.5 Turbo e GPT-4 Turbo per traduzioni di alta qualità
- **11 Lingue Supportate**: Italiano, Inglese, Spagnolo, Francese, Tedesco, Portoghese, Russo, Cinese, Giapponese, Coreano, Arabo
- **Traduzione In-Place**: Il contenuto viene tradotto direttamente nel post corrente
- **Debug Logging**: Sistema opzionale di logging per il debug delle chiamate API
- **Multisite**: Supporto per installazioni multisite (non network-enabled)

---

## Installazione

### Requisiti di Sistema

- WordPress 5.0 o superiore
- PHP 7.4 o superiore
- Chiave API OpenAI valida con billing configurato
- Connessione internet attiva per le chiamate API

### Procedura di Installazione

1. **Scarica il plugin**
   - Clona o scarica il repository nella cartella `/wp-content/plugins/`

2. **Attiva il plugin**
   - Vai su Plugin → Plugin installati
   - Cerca "Ride On WP Translator"
   - Clicca su "Attiva"

3. **Configura la chiave API**
   - Vai su Impostazioni → RideOn Translator
   - Inserisci la tua chiave API OpenAI
   - Configura le impostazioni predefinite

### Installazione Multisite

Il plugin **non è network-enabled**. Deve essere attivato individualmente per ogni sito nell'installazione multisite. Ogni sito avrà le proprie impostazioni indipendenti (chiave API, modello, lingue predefinite).

---

## Configurazione

### Ottenere una Chiave API OpenAI

1. Crea un account su [platform.openai.com](https://platform.openai.com)
2. Vai alla sezione API Keys
3. Crea una nuova chiave API
4. Copia la chiave (inizia con `sk-`)
5. Configura i limiti di billing secondo le tue necessità

### Impostazioni del Plugin

#### API Key
- **Campo**: Chiave API OpenAI
- **Formato**: Chiave che inizia con `sk-`
- **Sicurezza**: La chiave viene codificata in base64 prima dello storage
- **Obbligatorio**: Sì

#### Modello OpenAI
- **GPT-3.5 Turbo**: Più veloce e meno costoso, qualità buona
- **GPT-4 Turbo**: Qualità superiore, più costoso e leggermente più lento
- **Predefinito**: GPT-3.5 Turbo

#### Lingue Predefinite
- **Lingua Sorgente**: Lingua del contenuto originale (predefinito: Italiano)
- **Lingua Target**: Lingua di destinazione per le traduzioni (predefinito: Inglese)

#### Debug Logging
- **Abilita Logging**: Quando attivato, registra tutte le chiamate API in `wp-content/debug.log`
- **Requisito**: `WP_DEBUG_LOG` deve essere abilitato in `wp-config.php`
- **Utilizzo**: Utile per troubleshooting e monitoraggio delle chiamate API

---

## Utilizzo

### Traduzione di un Post

1. **Apri l'editor del post**
   - Vai su Post → Tutti i post
   - Clicca su "Modifica" sul post da tradurre

2. **Usa il metabox RideOn Translator**
   - Trova il metabox "RideOn Translator" nella sidebar destra
   - Seleziona la lingua sorgente (se diversa da quella predefinita)
   - Seleziona la lingua target dal dropdown
   - Clicca sul pulsante "Translate"

3. **Attendi il completamento**
   - Il plugin mostrerà un indicatore di caricamento
   - La traduzione può richiedere alcuni secondi a seconda della lunghezza del contenuto

4. **Salva il post**
   - Il contenuto viene tradotto direttamente nel post corrente
   - Rivedi la traduzione e salva quando pronto

### Cosa viene Tradotto

- **Titolo del post**: Tradotto completamente
- **Contenuto**: Testo completo del post (mantiene HTML e formattazione)
- **Excerpt**: Se presente, viene tradotto

### Cosa viene Copiato

- **Categorie**: Tutte le categorie del post originale
- **Tag**: Tutti i tag del post originale
- **Immagine in evidenza**: La stessa immagine viene associata al post tradotto
- **Autore**: Lo stesso autore del post originale
- **Tipo di post**: Mantiene lo stesso post type


---

## Architettura

### Struttura del Plugin

```
rideon-wp-translator/
├── admin/
│   ├── css/
│   │   └── admin.css
│   ├── js/
│   │   └── admin.js
│   └── views/
│       ├── metabox-translate.php
│       └── settings-page.php
├── includes/
│   ├── class-admin.php
│   ├── class-openai-client.php
│   ├── class-post-handler.php
│   └── class-translator.php
├── languages/
│   └── rideon-wp-translator.pot
└── rideon-wp-translator.php
```

### Classi Principali

#### `RideOn_WP_Translator`
Classe principale del plugin. Gestisce l'inizializzazione, il caricamento delle dipendenze e l'enqueue degli asset.

**Metodi principali:**
- `get_instance()`: Restituisce l'istanza singleton
- `load_dependencies()`: Carica tutte le classi necessarie
- `enqueue_admin_assets()`: Carica CSS e JavaScript nell'admin

#### `RideOn_Translator_Admin`
Gestisce la pagina delle impostazioni e l'interfaccia admin.

**Funzionalità:**
- Registrazione delle impostazioni
- Rendering della pagina settings
- Sanitizzazione dei dati di input
- Notifiche admin

#### `RideOn_Translator_OpenAI_Client`
Cliente per le chiamate all'API OpenAI.

**Metodi principali:**
- `translate($text, $source_lang, $target_lang)`: Traduce un testo
- `test_api_key()`: Testa la validità della chiave API
- `make_api_request($prompt)`: Esegue la chiamata HTTP all'API
- `parse_response($response)`: Elabora la risposta dell'API

**Gestione Errori:**
- 401: Chiave API non valida
- 429: Rate limit superato
- 500/503: Servizio temporaneamente non disponibile

#### `RideOn_Translator`
Orchestratore del processo di traduzione.

**Metodi principali:**
- `translate_post($post_id, $target_lang, $source_lang)`: Traduce un intero post e crea un nuovo post con lo stesso status dell'originale
- `get_translations($post_id, $target_lang, $source_lang)`: Ottiene solo le traduzioni senza creare un nuovo post (usato per traduzione in-place)
- `extract_post_content($post)`: Estrae titolo, contenuto ed excerpt
- `create_translated_post()`: Crea il post tradotto mantenendo lo stesso status del post originale

#### `RideOn_Translator_Post_Handler`
Gestisce il metabox e le richieste AJAX.

**Funzionalità:**
- Aggiunge il metabox all'editor dei post
- Gestisce le richieste AJAX per la traduzione
- Verifica permessi e nonce per sicurezza

### Flusso di Traduzione

1. **Utente clicca "Translate"**
   - JavaScript invia richiesta AJAX a `wp_ajax_rideon_translate_post`

2. **Post Handler verifica permessi**
   - Controlla nonce per sicurezza
   - Verifica capability `edit_posts`
   - Valida i parametri

3. **Translator estrae contenuto**
   - Legge titolo, contenuto ed excerpt dal post
   - Determina lingua sorgente (default o specificata)

4. **OpenAI Client traduce**
   - Costruisce il prompt di traduzione
   - Esegue chiamata API a OpenAI
   - Gestisce errori e timeout

5. **Translator aggiorna il post corrente**
   - Aggiorna titolo, contenuto ed excerpt con le traduzioni
   - Il post mantiene lo stesso status (pubblicato, bozza, ecc.)

6. **Risposta AJAX**
   - Restituisce le traduzioni (titolo, contenuto, excerpt)
   - JavaScript aggiorna i campi del post corrente
   - Mostra messaggio di successo

---

## API e Hook

### Filter Hooks

#### `rideon_translator_supported_post_types`
Filtra i post types supportati per la traduzione.

**Default**: `array('post')`

**Esempio:**
```php
add_filter('rideon_translator_supported_post_types', function($post_types) {
    $post_types[] = 'page';
    $post_types[] = 'custom_post_type';
    return $post_types;
});
```

### Action Hooks

Il plugin non espone action hooks personalizzati al momento, ma utilizza gli hook standard di WordPress:
- `plugins_loaded`: Per il caricamento del textdomain
- `admin_enqueue_scripts`: Per il caricamento degli asset
- `admin_menu`: Per l'aggiunta della pagina settings
- `add_meta_boxes`: Per l'aggiunta del metabox
- `wp_ajax_*`: Per le richieste AJAX

### Funzioni Pubbliche

#### `RideOn_Translator::translate_post()`
Traduce un post e crea un nuovo post tradotto con lo stesso status del post originale.

**Parametri:**
- `$post_id` (int): ID del post da tradurre
- `$target_lang` (string): Codice lingua target (es. `en`, `it`)
- `$source_lang` (string, opzionale): Codice lingua sorgente (usa default se vuoto)

**Ritorna:**
- `int`: ID del post tradotto creato
- `WP_Error`: In caso di errore

**Nota**: Il nuovo post mantiene lo stesso status del post originale (pubblicato, bozza, ecc.) e non vengono creati metadati di collegamento.

**Esempio:**
```php
$translator = new RideOn_Translator();
$translated_post_id = $translator->translate_post(123, 'en', 'it');
if (is_wp_error($translated_post_id)) {
    error_log($translated_post_id->get_error_message());
}
```

#### `RideOn_Translator::get_translations()`
Ottiene le traduzioni senza creare un nuovo post.

**Parametri:**
- `$post_id` (int): ID del post da tradurre
- `$target_lang` (string): Codice lingua target
- `$source_lang` (string, opzionale): Codice lingua sorgente

**Ritorna:**
- `array`: Array con chiavi `title`, `content`, `excerpt`
- `WP_Error`: In caso di errore

**Esempio:**
```php
$translator = new RideOn_Translator();
$translations = $translator->get_translations(123, 'en');
if (!is_wp_error($translations)) {
    echo $translations['title'];
    echo $translations['content'];
}
```

### Opzioni del Database

Il plugin salva le seguenti opzioni:

- `rideon_translator_api_key`: Chiave API codificata in base64
- `rideon_translator_model`: Modello OpenAI selezionato
- `rideon_translator_default_source_lang`: Lingua sorgente predefinita
- `rideon_translator_default_target_lang`: Lingua target predefinita
- `rideon_translator_enable_debug_log`: Abilitazione debug logging


---

## Sviluppo

### Setup Ambiente di Sviluppo

1. **Clona il repository**
   ```bash
   git clone <repository-url>
   cd rideon-wp-translator
   ```

2. **Configura WordPress locale**
   - Installa WordPress in modalità sviluppo
   - Abilita `WP_DEBUG` e `WP_DEBUG_LOG` in `wp-config.php`

3. **Installa dipendenze**
   - Nessuna dipendenza esterna richiesta (usa solo WordPress core)

### Struttura del Codice

Il plugin segue le WordPress Coding Standards:
- Naming conventions: `snake_case` per funzioni e variabili
- Class naming: `PascalCase` con prefisso `RideOn_`
- File naming: `kebab-case` per i file

### Testing

#### Test Manuali

1. **Test Traduzione Base**
   - Crea un post di test
   - Traduci in una lingua diversa
   - Verifica che il post tradotto sia creato correttamente

2. **Test Errori API**
   - Usa una chiave API non valida
   - Verifica che gli errori siano gestiti correttamente
   - Controlla i messaggi mostrati all'utente

3. **Test Debug Logging**
   - Abilita debug logging nelle impostazioni
   - Esegui una traduzione
   - Verifica i log in `wp-content/debug.log`

#### Debug

Per abilitare il debug:

1. Aggiungi in `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

2. Abilita "Enable Debug Logging" nelle impostazioni del plugin

3. Controlla `wp-content/debug.log` per i log delle chiamate API

### Estendere il Plugin

#### Aggiungere Nuove Lingue

Modifica l'array `$languages` in:
- `includes/class-admin.php` (metodo `render_language_select`)
- `includes/class-openai-client.php` (metodo `get_language_name`)
- `admin/views/metabox-translate.php`

Aggiungi il codice lingua e il nome della lingua in inglese.

#### Supportare Altri Post Types

Usa il filter `rideon_translator_supported_post_types`:

```php
add_filter('rideon_translator_supported_post_types', function($post_types) {
    $post_types[] = 'page';
    return $post_types;
});
```

#### Personalizzare il Prompt di Traduzione

Modifica il metodo `build_translation_prompt()` in `class-openai-client.php` per cambiare come viene costruito il prompt inviato a OpenAI.

---

## Troubleshooting

### Problemi Comuni

#### "OpenAI API key is not configured"
**Causa**: La chiave API non è stata inserita o salvata correttamente.

**Soluzione**:
1. Vai su Impostazioni → RideOn Translator
2. Verifica che la chiave API sia inserita correttamente
3. Assicurati che inizi con `sk-`
4. Salva le impostazioni

#### "Invalid API key"
**Causa**: La chiave API non è valida o è scaduta.

**Soluzione**:
1. Verifica la chiave su [platform.openai.com](https://platform.openai.com/api-keys)
2. Genera una nuova chiave se necessario
3. Assicurati che il billing sia configurato correttamente

#### "Rate limit exceeded"
**Causa**: Hai superato il limite di richieste API.

**Soluzione**:
1. Attendi alcuni minuti prima di riprovare
2. Verifica i limiti di billing su OpenAI
3. Considera di aumentare i limiti se necessario

#### Il post tradotto non viene creato
**Causa**: Possibili problemi di permessi o errori durante la creazione.

**Soluzione**:
1. Abilita debug logging
2. Controlla `wp-content/debug.log` per errori
3. Verifica che l'utente abbia i permessi `edit_posts`
4. Verifica che il post type sia supportato

#### La traduzione è incompleta o errata
**Causa**: Il contenuto potrebbe essere troppo lungo o il prompt non ottimale.

**Soluzione**:
1. Prova con GPT-4 Turbo per qualità migliore
2. Verifica che il contenuto non superi i limiti di token
3. Controlla i log per vedere la risposta completa dell'API

### Log e Debug

#### Abilitare Debug Logging

1. In `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

2. Nelle impostazioni del plugin:
   - Abilita "Enable Debug Logging"

3. Controlla i log:
   - File: `wp-content/debug.log`
   - Formato: `[RideOn Translator] <messaggio> | Context: <dati>`

#### Informazioni nei Log

I log includono:
- Richieste API (endpoint, modello, lunghezza prompt)
- Risposte API (status code, usage tokens)
- Errori dettagliati con codice e messaggio
- Contesto delle traduzioni (lunghezza testo, lingue)

### Supporto

Per problemi o domande:
- Controlla i log di debug
- Verifica la documentazione OpenAI API
- Apri una issue sul repository GitHub

---

## Changelog

### Versione 1.0.0
- Release iniziale
- Traduzione base di post WordPress
- Integrazione API OpenAI
- Pagina impostazioni admin
- Metabox traduzione post
- Supporto 11 lingue
- Debug logging opzionale
- Gestione errori API completa

---

## Licenza

GPL v2 or later

---

## Autore

Ride On Agency - [rideonagency.com](https://rideonagency.com)
