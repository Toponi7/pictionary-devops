terraform {
  required_providers {
    openstack = {
      source  = "terraform-provider-openstack/openstack"
      version = "~> 1.50.0"
    }
  }
}

# Le provider récupère automatiquement les variables d'environnement OS_*
provider "openstack" {
  auth_url    = "https://auth.cloud.ovh.net/v3"
  tenant_id   = "ac782cb2bd6442dfa69ced8526c8a095"
  tenant_name = "6633440012290193"
  region      = "BHS5"
}

# Définition des variables locales basées sur le Workspace Terraform actif
locals {
  # Mappe le nom du workspace (main ou preprod) vers le suffixe de nommage cible
  env_suffix = terraform.workspace == "main" ? "prod" : "preprod"

  # Permet de définir un gabarit (flavor) différent selon l'environnement
  instance_flavor = lookup({
    main    = "b3-8-flex"
    preprod = "b3-8-flex" # Modifiez cette valeur si la préproduction requiert un gabarit inférieur
  }, terraform.workspace, "b3-8-flex")
}

resource "openstack_compute_instance_v2" "k3s_node" {
  # Le nom devient dynamique : "pictionary-prod-node-1" ou "pictionary-preprod-node-1"
  name        = "pictionary-${local.env_suffix}-node-1"
  image_name  = "Debian 12"
  flavor_name = local.instance_flavor
  key_pair    = "rudy"

  network {
    name = "Ext-Net"
  }
}

# Permet d'afficher l'IP publique à la fin du déploiement
output "instance_ip" {
  value = openstack_compute_instance_v2.k3s_node.access_ip_v4
}
