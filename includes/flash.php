<?php
/** Affiche le flash de la page courante uniquement. */
echo flash_render(basename($_SERVER['PHP_SELF'] ?? ''));
