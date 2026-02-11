---
name: WordPress Translation Plugin
overview: Sviluppo di un plugin WordPress che traduce automaticamente articoli da una lingua A a una lingua B utilizzando le API di OpenAI. Il plugin deve essere attivato singolarmente per ogni site in un ambiente multisite (non è un plugin network).
todos:
  - id: setup-plugin-structure
    content: Creare struttura base plugin WordPress con file principale e directory
    status: completed
  - id: openai-client
    content: Implementare classe OpenAI Client con gestione API e autenticazione
    status: completed
  - id: translator-core
    content: Implementare classe Translator per orchestrazione processo traduzione
    status: completed
  - id: admin-settings
    content: Creare pagina impostazioni admin con form configurazione API key
    status: completed
  - id: post-metabox
    content: Implementare metabox nella pagina post con UI traduzione e gestione AJAX
    status: completed
  - id: post-handler
    content: Implementare logica creazione post tradotti e linking tra post originali/traduzioni
    status: completed
  - id: security-validation
    content: Aggiungere sanitizzazione input, nonce verification, capability checks e escape output
    status: completed
  - id: error-handling
    content: Implementare gestione errori completa (API, post, timeout)
    status: completed
  - id: styling-ui
    content: Aggiungere CSS e JavaScript per UI admin e loading states
    status: completed
  - id: documentation
    content: Scrivere README.md e documentazione codice
    status: completed
isProject: false
---

# Piano di Sviluppo: Plugin WordPress per Traduzione Automatica

## Obiettivi

- Plugin WordPress che traduce automaticamente articoli da una lingua sorgente a una lingua target
- Integrazione con API OpenAI per la traduzione
- Gestione configurazione API key da parte del cliente
- Interfaccia admin user-friendly nella pagina di modifica post
- Compatibilità multisite: il plugin deve essere attivato singolarmente per ogni site (non è un plugin network)

## Architettura del Plugin

### Struttura File

```
rideon-wp-translator/
├── rideon-wp-translator.php          # File principale del plugin
├── includes/
│   ├── class-translator.php          # Classe principale per gestione traduzioni
│   ├── class-openai-client.php       # Client per API OpenAI
│   ├── class-admin.php                # Interfaccia admin e settings
│   └── class-post-handler.php         # Gestione creazione post tradotti
├── admin/
│   ├── css/
│   │   └── admin.css                  # Stili admin
│   ├── js/
│   │   └── admin.js                   # JavaScript per UI admin
│   └── views/
│       ├── settings-page.php         # Pagina impostazioni
│       └── metabox-translate.php      # Metabox nella pagina post
├── languages/
│   └── rideon-wp-translator.pot      # File traduzione plugin
└── README.md
```

## Componenti Principali

### 1. File Principale (`rideon-wp-translator.php`)

- Header plugin WordPress standard
- Hook di attivazione/disattivazione
- Caricamento classi e inizializzazione
- Enqueue script e stili
- Registrazione hook WordPress
- **Compatibilità multisite**: Plugin non network-enabled, deve essere attivato singolarmente per ogni site

### 2. Classe OpenAI Client (`class-openai-client.php`)

- Metodo `translate()` per chiamate API OpenAI
- Gestione autenticazione con API key
- Gestione errori e retry logic
- Supporto per modello GPT-4-turbo o GPT-3.5-turbo (configurabile)

### 3. Classe Translator (`class-translator.php`)

- Orchestrazione processo traduzione
- Estrazione contenuto post (titolo, contenuto, excerpt, meta)
- Chiamata OpenAI Client
- Gestione post tradotto (creazione nuovo post o aggiornamento)
- Link tra post originale e traduzione (post meta)

### 4. Classe Admin (`class-admin.php`)

- Pagina impostazioni plugin
- Form configurazione API key OpenAI
- Validazione input

### 5. Classe Post Handler (`class-post-handler.php`)

- Metabox nella pagina di modifica post
- Selezione lingua target
- Pulsante traduzione con loading state
- Gestione AJAX per traduzione asincrona
- Visualizzazione stato traduzione e link al post tradotto

## Funzionalità Dettagliate

### Configurazione Plugin

- **Pagina Impostazioni**: Menu WordPress → Impostazioni → RideOn Translator
- **Campi configurazione**:
  - OpenAI API Key (campo password)
  - Modello OpenAI (dropdown: gpt-4-turbo-preview, gpt-3.5-turbo)
  - Lingua predefinita sorgente
  - Lingua predefinita target
- **Validazione**: Verifica API key con chiamata test
- **Salvataggio**: Opzioni WordPress (`update_option()`)
- **Multisite**: Le impostazioni sono salvate per ogni site individualmente

### Traduzione Post

- **Metabox nella pagina post**:
  - Selezione lingua target (dropdown)
  - Pulsante "Traduci"
  - Indicatore loading durante traduzione
  - Messaggio successo/errore
  - Link al post tradotto (se già esistente)
- **Processo traduzione**:
  1. Estrazione contenuto post (titolo, contenuto, excerpt, meta fields selezionati)
  2. Preparazione prompt per OpenAI con contesto traduzione
  3. Chiamata API OpenAI
  4. Creazione nuovo post WordPress con contenuto tradotto
  5. Link post originale ↔ traduzione (post meta `_translation_of` e `_translated_to`)
  6. Notifica successo/errore

## Sicurezza

- Sanitizzazione input utente (`sanitize_text_field()`, `wp_kses_post()`)
- Nonce verification per AJAX requests
- Capability check (`current_user_can('edit_posts')`)
- Escape output (`esc_html()`, `esc_attr()`)
- Validazione API key (formato e test connessione)

## Database Schema

- **Opzioni WordPress** (salvate per ogni site in multisite):
  - `rideon_translator_api_key`: API key OpenAI (criptata)
  - `rideon_translator_model`: Modello OpenAI selezionato
  - `rideon_translator_default_source_lang`: Lingua sorgente default
  - `rideon_translator_default_target_lang`: Lingua target default
- **Post Meta**:
  - `_translation_of`: ID post originale
  - `_translated_to`: Codice lingua traduzione

## Interfaccia Utente

- **Metabox post**: Design pulito con icona traduzione
- **Pagina impostazioni**: Form organizzato con sezioni
- **Notifiche**: Toast messages o admin notices per feedback
- **Loading states**: Spinner durante traduzione

## Gestione Errori

- API OpenAI non disponibile
- Post già tradotto nella lingua target
- Errore creazione post WordPress
- Timeout API
- Rate limiting OpenAI
- API key non valida o scaduta

## Testing

- Test unitari per classi principali
- Test integrazione API OpenAI (mock)
- Test creazione post tradotti
- Test sicurezza (nonce, capability)
- Test compatibilità multisite (attivazione per singolo site)

## Documentazione

- README.md con istruzioni installazione
- Commenti nel codice
- Documentazione API interna
- Guida utente per configurazione

## Compatibilità Multisite

- Il plugin **NON** è un plugin network-enabled
- Deve essere attivato singolarmente per ogni site nell'installazione multisite
- Ogni site ha le proprie impostazioni (API key, modello, lingue default)
- Le opzioni sono salvate a livello di site, non a livello di network

## Considerazioni Future (Non incluse nel MVP)

- Bulk translation (traduzione multipla post)
- Traduzione automatica su pubblicazione
- Supporto WPML/Polylang
- Cache traduzioni per evitare duplicati
- Dashboard avanzata con analytics
- Supporto multi-lingua (più di 2 lingue simultanee)
- Gestione budget e tracking costi (se richiesto in futuro)

