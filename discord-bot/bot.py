import os
import discord
import requests
import openstack
from discord import app_commands

DISCORD_TOKEN  = os.environ["DISCORD_TOKEN"]
GITHUB_TOKEN   = os.environ["GITHUB_TOKEN"]
GITHUB_REPO    = os.environ.get("GITHUB_REPO", "Toponi7/pictionary-devops")
GUILD_ID       = int(os.environ["DISCORD_GUILD_ID"])

GITHUB_API = f"https://api.github.com/repos/{GITHUB_REPO}"
GITHUB_HEADERS = {
    "Authorization": f"Bearer {GITHUB_TOKEN}",
    "Accept": "application/vnd.github+json",
    "X-GitHub-Api-Version": "2022-11-28",
}

STATUS_EMOJI = {
    "success":     "✅",
    "failure":     "❌",
    "in_progress": "⏳",
    "queued":      "🕐",
    "cancelled":   "⛔",
}

SERVER_STATUS_EMOJI = {
    "ACTIVE":  "🟢",
    "SHUTOFF": "🔴",
    "BUILD":   "🟡",
    "ERROR":   "💀",
}


def get_openstack_conn():
    return openstack.connect(
        auth_url=os.environ.get("OS_AUTH_URL", "https://auth.cloud.ovh.net/v3"),
        project_id=os.environ["OS_PROJECT_ID"],
        username=os.environ["OS_USERNAME"],
        password=os.environ["OS_PASSWORD"],
        region_name=os.environ.get("OS_REGION", "BHS5"),
        user_domain_name="Default",
        project_domain_name="Default",
    )


class PictionaryBot(discord.Client):
    def __init__(self):
        super().__init__(intents=discord.Intents.default())
        self.tree = app_commands.CommandTree(self)

    async def setup_hook(self):
        guild = discord.Object(id=GUILD_ID)
        self.tree.copy_global_to(guild=guild)
        await self.tree.sync(guild=guild)
        print(f"Slash commands synchronisés sur le serveur {GUILD_ID}")

    async def on_ready(self):
        print(f"Bot connecté en tant que {self.user}")


client = PictionaryBot()


# ── Autocomplete : liste les VMs OVH en temps réel ───────────────────────────

async def instance_autocomplete(
    interaction: discord.Interaction, current: str
) -> list[app_commands.Choice[str]]:
    try:
        conn = get_openstack_conn()
        servers = list(conn.compute.servers())
        return [
            app_commands.Choice(name=f"{s.name} ({s.status})", value=s.id)
            for s in servers
            if current.lower() in s.name.lower()
        ][:25]
    except Exception:
        return []


# ── /instances ────────────────────────────────────────────────────────────────

@client.tree.command(name="instances", description="Liste toutes les VMs OVH en cours")
async def instances(interaction: discord.Interaction):
    await interaction.response.defer(thinking=True)
    try:
        conn = get_openstack_conn()
        servers = list(conn.compute.servers())

        if not servers:
            await interaction.followup.send("Aucune instance en cours sur OVH.")
            return

        lines = []
        for s in servers:
            emoji = SERVER_STATUS_EMOJI.get(s.status, "❓")
            ip = "IP inconnue"
            for addrs in s.addresses.values():
                for addr in addrs:
                    if addr.get("version") == 4:
                        ip = addr["addr"]
                        break
            lines.append(f"{emoji} **{s.name}** — `{s.status}` — `{ip}`")

        await interaction.followup.send("\n".join(lines))

    except Exception as e:
        await interaction.followup.send(f"❌ Erreur OpenStack : `{e}`")


# ── /destroy ──────────────────────────────────────────────────────────────────

@client.tree.command(name="destroy", description="Détruit une VM OVH")
@app_commands.describe(instance="Instance à détruire (commence à taper pour filtrer)")
@app_commands.autocomplete(instance=instance_autocomplete)
async def destroy(interaction: discord.Interaction, instance: str):
    await interaction.response.defer(thinking=True)
    try:
        conn = get_openstack_conn()
        server = conn.compute.get_server(instance)
        conn.compute.delete_server(instance)
        await interaction.followup.send(
            f"💥 VM **{server.name}** en cours de suppression sur OVH."
        )
    except Exception as e:
        await interaction.followup.send(f"❌ Erreur OpenStack : `{e}`")


# ── /deploy ───────────────────────────────────────────────────────────────────

@client.tree.command(name="deploy", description="Déploie l'application sur un environnement")
@app_commands.describe(env="Environnement cible")
@app_commands.choices(env=[
    app_commands.Choice(name="prod (main)",  value="main"),
    app_commands.Choice(name="preprod",      value="preprod"),
])
async def deploy(interaction: discord.Interaction, env: str):
    await interaction.response.defer(thinking=True)

    resp = requests.post(
        f"{GITHUB_API}/actions/workflows/deploy-infra.yml/dispatches",
        headers=GITHUB_HEADERS,
        json={"ref": env},
    )

    if resp.status_code == 204:
        await interaction.followup.send(
            f"🚀 Déploiement lancé sur **{env}** !\n"
            f"Suivi : https://github.com/{GITHUB_REPO}/actions"
        )
    else:
        await interaction.followup.send(
            f"❌ Erreur GitHub API `{resp.status_code}` : {resp.text}"
        )


# ── /status ───────────────────────────────────────────────────────────────────

@client.tree.command(name="status", description="Affiche le statut des 3 derniers workflows")
async def status(interaction: discord.Interaction):
    await interaction.response.defer(thinking=True)

    resp = requests.get(f"{GITHUB_API}/actions/runs?per_page=3", headers=GITHUB_HEADERS)

    if resp.status_code != 200:
        await interaction.followup.send("❌ Impossible de récupérer le statut.")
        return

    runs = resp.json().get("workflow_runs", [])
    if not runs:
        await interaction.followup.send("Aucun workflow trouvé.")
        return

    lines = []
    for run in runs:
        conclusion = run.get("conclusion") or run.get("status", "?")
        emoji = STATUS_EMOJI.get(conclusion, "❓")
        lines.append(
            f"{emoji} **{run['name']}** — `{run['head_branch']}` — {conclusion}"
        )

    await interaction.followup.send("\n".join(lines))


client.run(DISCORD_TOKEN)
