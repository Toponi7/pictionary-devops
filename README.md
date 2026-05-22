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
- [Démo 1 — Destruction et redéploiement complet](#démo-1--destruction-et-redéploiement-complet)
- [Démo 2 — Intégration continue vers la préprod](#démo-2--intégration-continue-vers-la-préprod)
- [Monitoring & Observabilité](#monitoring--observabilité)
- [Environnements](#environnements)

---

## Architecture globale

```
┌─────────────────────────────────────────────────────────────────────┐
│                          GitHub Repository                          │
│                                                                     │
│   branch: main          ──────────────────►  prod                  │
│   branch: preprod       ──────────────────►  preprod               │
└────────────┬───────────────────────────────────────────────────────┘
             │ git push (src/ ou Dockerfile)
             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                       GitHub Actions (CI/CD)                        │
│                                                                     │
│  [1] docker-build.yml              [2] deploy-infra.yml            │
│  Build image PHP                   Terraform workspace              │
│  Push GHCR :latest / :preprod ──►  Ansible playbook                │
│  Déclenche (2) si succès           Validations automatisées        │
└──────────┬──────────────────────────────┬───────────────────────────┘
           │                              │
           ▼                              ▼
┌──────────────────┐           ┌──────────────────────────────────────┐
│  GHCR            │           │  OVH Public Cloud (OpenStack)        │
│                  │           │                                      │
│  :latest         │           │  ┌──────────────────────────────┐   │
│  :preprod-latest │           │  │  VM Debian 12 (b3-8-flex)    │   │
│  :sha-xxxxxxx    │           │  │                              │   │
│  :V{n}           │           │  │  K3s (Kubernetes léger)      │   │
└──────────────────┘           │  │                              │   │
                               │  │  ┌──────────┐ ┌──────────┐  │   │
                               │  │  │ Pod web  │ │ Pod web  │  │   │
                               │  │  │ PHP x2   │ │ PHP x2   │  │   │
                               │  │  └────┬─────┘ └─────┬────┘  │   │
                               │  │       └──────┬───────┘       │   │
                               │  │         ┌────▼─────┐         │   │
                               │  │         │ Pod DB   │         │   │
                               │  │         │ MariaDB  │         │   │
                               │  │         └──────────┘         │   │
                               │  │                              │   │
                               │  │  ┌─── Monitoring ─────────┐  │   │
                               │  │  │ Prometheus  (interne)  │  │   │
                               │  │  │ Grafana     :30080     │  │   │
                               │  │  │ Loki        :3100      │  │   │
                               │  │  │ Promtail    (agent)    │  │   │
                               │  │  └───────────────────────┘  │   │
                               │  └──────────────────────────────┘   │
                               └──────────────────────────────────────┘
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
| **Infrastructure** | Terraform + OpenStack OVH | Provisionnement de la VM cloud |
| **Configuration** | Ansible | Installation K3s, app, monitoring |
| **Métriques** | Prometheus + Grafana (Helm) | Dashboards & alerting |
| **Logs** | Loki + Promtail (Helm) | Collecte et consultation des logs |
| **CI/CD** | GitHub Actions | Automatisation build → test → deploy |

---

## Structure du dépôt

```
pictionary-devops/
│
├── src/
│   └── index.php                    # Application PHP (frontend + API AJAX)
│
├── kubernetes/
│   └── pictionary-app.yaml          # Manifestes K8s :
│                                    #   - ConfigMap (init SQL)
│                                    #   - Deployment MariaDB
│                                    #   - Deployment web (2 replicas, RollingUpdate)
│                                    #     ↳ initContainer : attend MariaDB
│                                    #     ↳ readinessProbe + livenessProbe HTTP
│                                    #   - Services + Ingress
│
├── terraform/
│   └── main.tf                      # VM OVH via OpenStack, workspaces prod/preprod
│
├── ansible/
│   ├── playbook.yml                 # Installe K3s, déploie l'app,
│   │                                # Prometheus+Grafana, Loki+Promtail,
│   │                                # dashboard Grafana provisionné
│   └── files/
│       └── pictionary-dashboard.json  # Dashboard Grafana custom (injecté via ConfigMap)
│
├── .github/workflows/
│   ├── docker-build.yml             # Build & push image GHCR sur push src/ ou Dockerfile
│   └── deploy-infra.yml             # Terraform + Ansible + 3 validations automatisées
│
├── Dockerfile                       # Image PHP 8.2-apache + PDO MySQL
├── init.sql                         # Création table `mots` + données initiales
└── docker-compose.yml               # Stack locale : web + MariaDB (dev uniquement)
```

---

## Secrets GitHub requis

À configurer dans **Settings → Secrets and variables → Actions** du dépôt :

| Secret | Description |
|---|---|
| `OS_USERNAME` | Identifiant OpenStack OVH |
| `OS_PASSWORD` | Mot de passe OpenStack OVH |
| `SSH_PRIVATE_KEY` | Clé SSH privée correspondant à la keypair `rudy` sur OVH |
| `GITHUB_TOKEN` | Automatiquement injecté par GitHub — aucune action requise |

---

## Démo 1 — Destruction et redéploiement complet

> **Scénario** : On simule une catastrophe totale — la VM de production est détruite. On démontre que l'infrastructure entière peut être recréée et l'application redéployée en **une seule commande**, sans intervention manuelle.

### Ce qui se passe sous le capot

```
terraform destroy  ──►  VM supprimée sur OVH
        │
        └──►  workflow_dispatch sur main
                      │
              [Terraform Apply]
              ├── Crée une nouvelle VM Debian 12 sur OVH
              └── Récupère l'IP publique
                      │
              [Ansible Playbook]
              ├── Étape 0 : Configure l'accès GHCR sur K3s
              ├── Étape 1 : Installe K3s + attend l'API (port 6443)
              ├── Étape 2 : Applique pictionary-app.yaml
              │            (2 pods web avec initContainer + probes,
              │             1 pod MariaDB, Service + Ingress)
              ├── Étape 3 : Installe Helm
              ├── Étape 4 : Déploie Prometheus + Grafana (NodePort 30080)
              ├── Étape 5 : Déploie Loki + Promtail
              │            (collecte automatique des logs de tous les pods)
              │            (datasource Loki configurée dans Grafana)
              └── Étape 6 : Injecte le dashboard Grafana custom via ConfigMap
                      │
              ✅ Tout est de nouveau en ligne
```

### Étapes de la démo

**1. Détruire l'infrastructure existante**

```bash
cd terraform
terraform workspace select main
terraform destroy -auto-approve
```

> La VM disparaît du dashboard OVH. L'application n'est plus accessible.

**2. Relancer le déploiement complet via GitHub Actions**

Aller sur : `Actions → Deploy Infrastructure → Run workflow → Branch: main`

Ou via CLI :

```bash
gh workflow run deploy-infra.yml --ref main
```

**3. Suivre le pipeline en direct**

```
✅ Checkout du code
✅ Terraform init / workspace main / apply  ──►  nouvelle VM créée, IP récupérée
✅ Attente SSH (port 22 ouvert sur la VM)
✅ Ansible playbook
   ├── K3s installé et opérationnel
   ├── pictionary-app.yaml appliqué
   │     ├── Pod MariaDB démarré
   │     └── 2 pods web (initContainer attend MariaDB, probes OK)
   ├── Prometheus + Grafana déployés  ──►  :30080
   ├── Loki + Promtail déployés       ──►  logs collectés
   └── Dashboard Pictionary injecté dans Grafana
✅ Application accessible sur l'IP de la nouvelle VM
```

**4. Résultat**

- Application accessible sur `http://<IP_VM>` (port 80)
- Grafana accessible sur `http://<IP_VM>:30080` (admin / admin)
- Dashboard custom Pictionary visible dans Grafana
- Logs de l'application disponibles dans Grafana → Loki
- Zéro intervention manuelle après le `terraform destroy`

---

## Démo 2 — Intégration continue vers la préprod

> **Scénario** : Un développeur modifie le code de l'application. Il pousse sur la branche `preprod`. En quelques minutes, la modification est **automatiquement déployée, testée et validée sur la préprod**, sans toucher la production.

### Ce qui se passe sous le capot

```
git push origin preprod  (modif dans src/)
        │
        ▼
[docker-build.yml]
  Build de l'image Docker
  Push vers GHCR :
    ├── preprod-latest
    ├── preprod-V{run_number}
    └── sha-{7 chars du commit}
        │
        └──► succès ──► [deploy-infra.yml déclenché automatiquement]
                              │
                      TARGET_ENV = preprod
                      IMAGE_TAG  = sha-xxxxxxx
                              │
                      [Terraform]
                      workspace preprod
                      ──► VM "pictionary-preprod-node-1" (créée si absente)
                              │
                      [Ansible]
                      Remplace le tag image dans pictionary-app.yaml
                      k3s kubectl apply  ──►  RollingUpdate sans downtime
                              │
                      [Validations automatisées]
                      ├── ✅ Validation 1 : Pods stables (RESTARTS = 0)
                      ├── ✅ Validation 2 : Smoke test HTTP 200
                      └── ✅ Validation 3 : Pas d'erreur critique dans les logs
                              │
                      ✅ Préprod validée — prod intacte
```

### Étapes de la démo

**1. Faire une modification dans le code**

Par exemple, dans `src/index.php`, modifier le titre :

```php
// Avant
<h1>Pictionary !</h1>

// Après
<h1>Pictionary ! 🚀 v2</h1>
```

**2. Pousser sur la branche `preprod`**

```bash
git checkout preprod
git add src/index.php
git commit -m "feat: mise à jour titre v2"
git push origin preprod
```

**3. Observer les pipelines s'enchaîner dans GitHub Actions**

```
[docker-build.yml]
  ✅ Build image  ──►  Push :preprod-latest + :sha-abc1234

[deploy-infra.yml]  (déclenché automatiquement après le build)
  ✅ Terraform workspace preprod  ──►  VM preprod provisionnée
  ✅ Ansible  ──►  Nouveau tag sha-abc1234 déployé en RollingUpdate
  ✅ Validation 1  ──►  k3s kubectl rollout status + RESTARTS = 0
  ✅ Validation 2  ──►  curl http://<IP_preprod> → 200 OK
  ✅ Validation 3  ──►  grep logs → aucune erreur critique
```

**4. Vérifier sur la préprod**

L'IP de la VM préprod est affichée dans les logs Terraform du pipeline.
La modification est visible — la prod (`main`) est **inchangée**.

### Détail des validations automatisées (préprod uniquement)

| # | Validation | Ce qui est vérifié | Échec si… |
|---|---|---|---|
| 1 | **Stabilité des pods** | `kubectl rollout status` + compteur de redémarrages | Un pod a redémarré (`RESTARTS > 0`) |
| 2 | **Smoke test HTTP** | `curl http://<IP>` pendant 60s | Aucun `200 OK` reçu |
| 3 | **Analyse des logs** | 200 dernières lignes des pods web | `exception`, `fatal error`, `SQL error`, `connection refused` détectés |

---

## Monitoring & Observabilité

La stack complète est déployée automatiquement par Ansible via Helm sur chaque environnement.

### Accès

| Outil | Accès | Identifiants | Rôle |
|---|---|---|---|
| **Grafana** | `http://<IP_VM>:30080` | `admin` / `admin` | Dashboards métriques + logs |
| **Prometheus** | Interne au cluster | — | Collecte des métriques K8s |
| **Loki** | `http://loki.monitoring.svc.cluster.local:3100` | — | Agrégation des logs |
| **Promtail** | Agent sur chaque pod | — | Envoi des logs vers Loki |

### Dashboards disponibles dans Grafana

- **kube-prometheus-stack** : métriques CPU, mémoire, réseau, état des pods (inclus automatiquement)
- **Pictionary Dashboard** : dashboard custom (`pictionary-dashboard.json`) injecté via ConfigMap et détecté automatiquement par Grafana au label `grafana_dashboard=1`

### Consulter les logs applicatifs dans Grafana

1. Ouvrir Grafana → **Explore**
2. Sélectionner la datasource **Loki**
3. Utiliser le filtre : `{app="web"}`

---

## Environnements

### Développement local

```bash
docker compose up --build
# Accès : http://localhost:8080
```

### Cloud (prod & préprod)

Tout est piloté par GitHub Actions. Aucune commande manuelle requise en dehors des démos.

Pour un déploiement manuel d'urgence :

```bash
cd terraform
terraform workspace select main   # ou preprod
terraform apply -auto-approve

# Récupérer l'IP
terraform output instance_ip

# Lancer Ansible manuellement
ansible-playbook -i "IP_VM," -u debian \
  --private-key ~/.ssh/id_rsa \
  -e "ghcr_username=TON_USER ghcr_password=TON_TOKEN image_tag=latest" \
  ansible/playbook.yml
```

### Comparatif prod / préprod

| | Production (`main`) | Préprod (`preprod`) |
|---|---|---|
| **VM** | `pictionary-prod-node-1` | `pictionary-preprod-node-1` |
| **Image tag** | `:latest`, `:V{n}` | `:preprod-latest`, `:preprod-V{n}` |
| **Déclencheur deploy** | Push `main` ou `workflow_dispatch` | Après build réussi sur `preprod` |
| **Workspace Terraform** | `main` | `preprod` |
| **Validations auto** | Non | Oui (pods, HTTP 200, logs) |
| **Rolling update** | Non | Oui (`maxSurge: 1`, `maxUnavailable: 0`) |
