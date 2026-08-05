# Procédure d’audit de sécurité

Les audits sont indépendants de Codex et ne consomment aucun token ChatGPT. Les workflows `Manual secret and identity guard` et `Manual repository security audit` sont déclenchés uniquement à la demande depuis **Actions**.

L’identité publique autorisée est `ussmarines` et `https://github.com/ussmarines`. Le garde analyse l’arbre courant, les métadonnées de commits et les blobs historiques à partir d’empreintes SHA-256. Les identifiants civils recherchés ou détectés ne sont jamais affichés. Toute nécessité légale d’identité civile doit être validée par le propriétaire avant modification.

Première exécution GitHub : choisir `full` et `report`, puis télécharger l’artefact JSON conservé 30 jours. Après triage, `block` active une validation stricte.

Les occurrences historiques déjà triées peuvent être approuvées uniquement dans `.security/approved-historical-identity-findings.json`. Chaque entrée doit correspondre exactement à un blob historique, un chemin, une ligne et la catégorie autorisée. Cette liste ne peut jamais approuver l’arbre courant, une métadonnée de commit ou une nouvelle occurrence. Une entrée inconnue, dupliquée, mal formée ou devenue obsolète provoque un échec fermé afin d’imposer un nouveau triage.

Sous Windows, lancer une seule fois `tools/security/install-security-tools.ps1`. Les outils partagés sont placés dans `%LOCALAPPDATA%\ussmarines-security-tools`. Lancer ensuite `tools/security/security-scan.ps1 -Profile Full` dans chaque dépôt. Les rapports restent dans `tools/security/.reports/` et ne sont jamais suivis par Git.

Ne jamais copier une valeur détectée dans un ticket, prompt ou log. Révoquer immédiatement tout secret exposé. Une réécriture destructive de l’historique nécessite une autorisation séparée.
