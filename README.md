# 🎨 Pictionary DevOps

> Application web de génération de mots Pictionary, déployée sur une infrastructure cloud complète et automatisée.

[![Build Docker](https://github.com/Toponi7/pictionary-devops/actions/workflows/docker-build.yml/badge.svg)](https://github.com/Toponi7/pictionary-devops/actions/workflows/docker-build.yml)
[![Deploy Infra](https://github.com/Toponi7/pictionary-devops/actions/workflows/deploy-infra.yml/badge.svg)](https://github.com/Toponi7/pictionary-devops/actions/workflows/deploy-infra.yml)

---

## Table des matières

- [Architecture globale](#architecture-globale)
- [Stack technique](#stack-technique)
- [Structure du dépôt](#structure-du-dépôt)
- [Secrets GitHub requis](#secrets-github-requis)
- [Bot Discord](#bot-discord)
- [Démo 1 — Destruction et redéploiement complet](#démo-1--destruction-et-redéploiement-complet)
- [Démo 2 — Intégration continue vers la préprod](#démo-2--intégration-continue-vers-la-préprod)
- [Monitoring & Observabilité](#monitoring--observabilité)
- [Environnements](#environnements)

---

## Architecture globale

```
┌─────────────────────────────────────────────────────────────────────┐
│                          GitHub Repository                          │
│   branch: main          ──────────────────►  prod                  │
│   branch: preprod       ──────────────────►  preprod               │
└────────────┬───────────────────────────────────────────────────────┘
             │ git push (src/ ou Dockerfile)
             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                       GitHub Actions (CI/CD)                        │
│                                                                     │
│  [1] docker-build.yml       [2] deploy-infra.yml                   │
│  Build & push image GHCR    Terraform + Ansible + validations       │
│                                      │                              │
│                             [3] promote-to-production.yml           │
│                             Notif Discord ──► approbation ──► prod  │
│                                                                     │
│  [4] terraform-destroy.yml  (déclenché via bot Discord)             │
└──────────┬──────────────────────────────┬───────────────────────────┘
           │                              │
           ▼                              ▼
┌──────────────────┐      ┌───────────────────────────────────────────┐
│  GHCR            │      │  OVH Public Cloud (OpenStack)             │
│  :latest         │      │                                           │
│  :preprod-latest │      │  ┌─────────────────────────────────────┐  │
│  :sha-xxxxxxx    │      │  │  VM Debian 12 (b3-8-flex)           │  │
│  :V{n}           │      │  │  K3s ── 2× Pod web (PHP)            │  │
└──────────────────┘      │  │       ── Pod DB (MariaDB)           │  │
                          │  │  Prometheus · Grafana :30080        │  │
                          │  │  Loki · Promtail                    │  │
                          │  └─────────────────────────────────────┘  │
                          └───────────────────────────────────────────┘
                                           ▲
┌──────────────────────────────────────────┤
│  Bot Discord (Docker local)              │
│  /deploy   ──►  GitHub Actions API       │
│  /destroy  ──►  OpenStack API (direct)   │
│  /instances──►  OpenStack API (direct)   │
│  /status   ──►  GitHub Actions API       │
└──────────────────────────────────────────┘
```

---

## Stack technique

| Couche | Technologie | Rôle |
|---|---|---|
| **Application** | PHP 8.2 + Apache | Serveur web & logique métier |
| **Base de données** | MariaDB 10.11 | Stockage des mots Pictionary |
| **Conteneurisation** | Docker | Build & packaging de l'image |
| **Registre d'images** | GHCR (GitHub Container Registry) | Stockage versionné des images |
| **Orchestration** | K3s (Kubernetes léger) | Déploiement, scaling, rolling update |
| **Infrastructure** | Terraform + OpenStack OVH | Provisionnement des VMs cloud |
| **Configuration** | Ansible | Installation K3s, app, monitoring |
| **Métriques** | Prometheus + Grafana (Helm) | Dashboards & alerting |
| **Logs** | Loki + Promtail (Helm) | Collecte et consultation des logs |
| **CI/CD** | GitHub Actions | Automatisation build → test → deploy |
| **Bot DevOps** | Python + discord.py + openstacksdk | Pilotage de l'infra depuis Discord |

---

## Structure du dépôt

```
pictionary-devops/
│
├── src/
│   └── index.php                      # Application PHP (frontend + API AJAX)
│
├── kubernetes/
│   └── pictionary-app.yaml            # ConfigMap, Deployments (RollingUpdate,
│                                      # initContainer, probes), Services, Ingress
│
├── terraform/
│   └── main.tf                        # VM OVH via OpenStack, workspaces prod/preprod
│
├── ansible/
│   ├── playbook.yml                   # K3s, app, Prometheus+Grafana, Loki+Promtail,
│   │                                  # dashboard custom provisionné
│   └── files/dashboards/
│       └── pictionary-dashboard.json  # Dashboard Grafana injecté via ConfigMap
│
├── discord-bot/
│   ├── bot.py                         # Bot Discord : /deploy /destroy /instances /status
│   ├── Dockerfile                     # Image Python 3.12-slim
│   ├── docker-compose.yml             # Lancement local du bot
│   ├── requirements.txt               # discord.py, requests, openstacksdk
│   └── .env.example                   # Template des variables d'environnement
│
├── .github/workflows/
│   ├── docker-build.yml               # Build & push image GHCR
│   ├── deploy-infra.yml               # Terraform + Ansible + 3 validations
│   ├── promote-to-production.yml      # Notif Discord + approbation + promotion prod
│   └── terraform-destroy.yml         # Destruction ciblée via workflow_dispatch
│
├── Dockerfile                         # Image PHP 8.2-apache + PDO MySQL
├── init.sql                           # Création table `mots` + données initiales
└── docker-compose.yml                 # Stack locale dev : web + MariaDB
```

---

## Secrets GitHub requis

À configurer dans **Settings → Secrets and variables → Actions** :

| Secret | Description |
|---|---|
| `OS_USERNAME` | Identifiant OpenStack OVH |
| `OS_PASSWORD` | Mot de passe OpenStack OVH |
| `SSH_PRIVATE_KEY` | Clé SSH privée (keypair `rudy` sur OVH) |
| `DISCORD_WEBHOOK_URL` | URL webhook Discord pour les notifications CI/CD |
| `GITHUB_TOKEN` | Automatiquement injecté par GitHub |

---

## Bot Discord

Le bot tourne en local dans Docker et permet de piloter l'infrastructure directement depuis Discord.

### Commandes disponibles

| Commande | Action |
|---|---|
| `/deploy [prod\|preprod]` | Déclenche `deploy-infra.yml` via l'API GitHub Actions |
| `/destroy` | Liste les VMs OVH en autocomplete, supprime l'instance choisie via l'API OpenStack |
| `/instances` | Affiche toutes les VMs OVH avec leur statut et IP |
| `/status` | Affiche le statut des 3 derniers workflow runs |

### Lancement

```bash
# 1. Copier et remplir le fichier de config
cp discord-bot/.env.example discord-bot/.env

# 2. Lancer le bot
cd discord-bot
docker compose up --build
```

### Variables requises dans `discord-bot/.env`

```env
DISCORD_TOKEN=...       # Discord Developer Portal → Bot → Reset Token
DISCORD_GUILD_ID=...    # Clic droit sur ton serveur → Copier l'identifiant
GITHUB_TOKEN=...        # PAT GitHub avec scope Actions (read & write)
GITHUB_REPO=Toponi7/pictionary-devops
OS_USERNAME=...         # Identifiants OVH
OS_PASSWORD=...
OS_PROJECT_ID=ac782cb2bd6442dfa69ced8526c8a095
OS_REGION=BHS5
```

---

## Démo 1 — Destruction et redéploiement complet

> **Scénario** : On simule une catastrophe totale. La VM de production est détruite depuis Discord. On démontre que l'infrastructure entière est recréée et l'application redéployée automatiquement, sans intervention manuelle.

### Flow complet

```
Discord : /destroy  ──►  autocomplete liste les VMs OVH
                    ──►  sélection "pictionary-prod-node-1"
                    ──►  suppression directe via API OpenStack
                                  │
                         VM disparaît du dashboard OVH
                                  │
Discord : /deploy prod ──►  GitHub Actions : deploy-infra.yml
                                  │
                        [Terraform Apply]
                        Crée une nouvelle VM Debian 12 sur OVH
                                  │
                        [Ansible Playbook]
                        ├── K3s installé
                        ├── pictionary-app.yaml appliqué
                        │     (2 pods web + MariaDB + Ingress)
                        ├── Prometheus + Grafana  ──►  :30080
                        ├── Loki + Promtail       ──►  logs collectés
                        └── Dashboard Grafana injecté
                                  │
                        ✅ Tout est de nouveau en ligne
```

### Étapes de la démo

**1. Détruire la VM depuis Discord**

```
/destroy  →  sélectionner "pictionary-prod-node-1 (ACTIVE)"  →  Entrée
```

> La VM disparaît du dashboard OVH. L'application n'est plus accessible.

**2. Relancer le déploiement complet**

```
/deploy prod
```

Ou via GitHub Actions : `Actions → Deploy Infrastructure → Run workflow → Branch: main`

**3. Suivre le pipeline**

```
✅ Terraform apply  ──►  nouvelle VM créée, IP récupérée
✅ Ansible
   ├── K3s opérationnel
   ├── 2 pods web + MariaDB démarrés
   ├── Grafana :30080
   └── Loki + dashboard injectés
✅ Application accessible sur la nouvelle IP
```

---

## Démo 2 — Intégration continue vers la préprod

> **Scénario** : Un développeur pousse une modification sur `preprod`. Elle est automatiquement déployée, testée, puis une notification Discord demande une validation humaine avant promotion en production.

### Flow complet

```
git push origin preprod  (modif dans src/)
        │
[docker-build.yml]  ──►  Push :preprod-latest + :sha-abc1234
        │
        └──► succès ──► [deploy-infra.yml]
                              │
                        Terraform workspace preprod
                        Ansible ──► RollingUpdate sha-abc1234
                              │
                        Validations automatisées :
                        ├── ✅ Pods stables (RESTARTS = 0)
                        ├── ✅ Smoke test HTTP 200
                        └── ✅ Logs sans erreur critique
                              │
                        ✅ Préprod OK
                              │
                        [promote-to-production.yml]
                              │
                        notify-discord  ──►  Discord :
                        │               "⏳ Préprod validée
                        │                En attente d'approbation prod
                        │                👉 [lien GitHub]"
                        │
                        promote  ──►  [attend l'approbation humaine]
                              │
                        quelqu'un approuve sur GitHub
                              │
                        ├── Re-tag :preprod-latest → :latest sur GHCR
                        ├── Terraform workspace main ──► VM prod
                        ├── Validations prod (pods, HTTP, logs)
                        └── Rollback automatique si échec
```

### Étapes de la démo

**1. Modifier le code**

```php
// src/index.php
<h1>Pictionary ! 🚀 v2</h1>
```

**2. Pousser sur `preprod`**

```bash
git checkout preprod
git add src/index.php
git commit -m "feat: mise à jour titre v2"
git push origin preprod
```

**3. Observer dans GitHub Actions**

```
docker-build.yml      ✅  Build + Push :preprod-latest
deploy-infra.yml      ✅  Deploy preprod + 3 validations
promote-to-production ⏳  Notification Discord envoyée → en attente
```

**4. Recevoir la notification Discord**

```
⏳ Promotion en attente de validation
La préprod est déployée et validée.
Une approbation manuelle est requise → [lien]
```

**5. Approuver sur GitHub → prod déployée automatiquement**

### Détail des validations automatisées

| # | Validation | Ce qui est vérifié | Échec si… |
|---|---|---|---|
| 1 | **Stabilité des pods** | `kubectl rollout status` + `RESTARTS` | Un pod a redémarré |
| 2 | **Smoke test HTTP** | `curl http://<IP>` pendant 60s | Aucun `200 OK` |
| 3 | **Analyse des logs** | 200 dernières lignes des pods | `exception`, `fatal error`, `SQL error` détectés |

---

## Monitoring & Observabilité

Déployé automatiquement par Ansible via Helm sur chaque environnement.

| Outil | Accès | Identifiants | Rôle |
|---|---|---|---|
| **Grafana** | `http://<IP_VM>:30080` | `admin` / `admin` | Dashboards métriques + logs |
| **Prometheus** | Interne au cluster | — | Collecte des métriques K8s |
| **Loki** | Interne au cluster (DNS K8s uniquement) | — | Agrégation des logs |
| **Promtail** | Agent sur chaque pod | — | Envoi des logs vers Loki |

### Consulter les logs dans Grafana

1. Grafana → **Explore**
2. Datasource : **Loki**
3. Filtre : `{app="web"}`

---

## Environnements

### Développement local

```bash
docker compose up --build
# Accès : http://localhost:8080
```

### Comparatif prod / préprod

| | Production (`main`) | Préprod (`preprod`) |
|---|---|---|
| **VM** | `pictionary-prod-node-1` | `pictionary-preprod-node-1` |
| **Image tag** | `:latest`, `:V{n}` | `:preprod-latest`, `:preprod-V{n}` |
| **Déclencheur deploy** | `/deploy prod` ou `workflow_dispatch` | Push `preprod` |
| **Workspace Terraform** | `main` | `preprod` |
| **Validations auto** | Oui (pods, HTTP, logs) | Oui (pods, HTTP, logs) |
| **Rolling update** | Oui | Oui (`maxSurge: 1`, `maxUnavailable: 0`) |
| **Promotion** | Via approbation Discord + GitHub | — |
| **Rollback auto** | Oui (digest précédent) | — |
