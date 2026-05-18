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
auth_url = "https://auth.cloud.ovh.net/v3"
tenant_id = "ac782cb2bd6442dfa69ced8526c8a095"
tenant_name = "6633440012290193"
region = "BHS5"
}

resource "openstack_compute_instance_v2" "k3s_node" {
  name        = "pictionary-prod-node-1" # Nom de l'instance pour le projet
  image_name  = "Debian 12"             # L'image système désirée
  flavor_name = "c3-8-flex"                  # Gabarit chez OVH (ex: 2 vCores, 4 Go RAM)
  key_pair    = "rudy"     # Le nom de la clé SSH préalablement ajoutée sur OVH

  network {
    name = "Ext-Net" # Réseau public par défaut d'OVH
  }
}

# Permet d'afficher l'IP publique à la fin du déploiement
output "instance_ip" {
  value = openstack_compute_instance_v2.k3s_node.access_ip_v4
}
