<?php
// config_conekta.php
// Llaves de Conekta (puedes configurarlas con variables de entorno)
// Windows (PowerShell):
//   setx CONEKTA_PUBLIC_KEY "key_xxx"
//   setx CONEKTA_PRIVATE_KEY "key_xxx"
// Linux/Mac:
//   export CONEKTA_PUBLIC_KEY="key_xxx"
//   export CONEKTA_PRIVATE_KEY="key_xxx"

$CONEKTA_PUBLIC_KEY = getenv('CONEKTA_PUBLIC_KEY') ?: 'key_LlllReCA7bWfOVv8D0a6EnW';
$CONEKTA_PRIVATE_KEY = getenv('CONEKTA_PRIVATE_KEY') ?: 'key_rsBqNVAEX78CIsYKkmPwLNH';
$CONEKTA_API_BASE = 'https://api.conekta.io';
$CONEKTA_API_ACCEPT = 'application/vnd.conekta-v2.0.0+json';
?>
