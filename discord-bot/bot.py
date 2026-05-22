import os
import discord
import requests
from discord import app_commands

DISCORD_TOKEN  = os.environ["DISCORD_TOKEN"]
GITHUB_TOKEN   = os.environ["GITHUB_TOKEN"]
GITHUB_REPO    = os.environ.get("GITHUB_REPO", "Toponi7/pictionary-devops")
GUILD_ID       = int(os.environ["DISCORD_GUILD_ID"])

GITHUB_API = f"https://api.github.com/repos/{GITHUB_REPO}"
HEADERS = {
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

ENV_CHOICES = [
    app_commands.Choice(name="prod (main)",  value="main"),
    app_commands.Choice(name="preprod",      value="preprod"),
]


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


@client.tree.command(name="deploy", description="Déploie l'application sur un environnement")
@app_commands.describe(env="Environnement cible")
@app_commands.choices(env=ENV_CHOICES)
async def deploy(interaction: discord.Interaction, env: str):
    await interaction.response.defer(thinking=True)

    resp = requests.post(
        f"{GITHUB_API}/actions/workflows/deploy-infra.yml/dispatches",
        headers=HEADERS,
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


@client.tree.command(name="destroy", description="Détruit l'infrastructure d'un environnement")
@app_commands.describe(env="Environnement à détruire")
@app_commands.choices(env=ENV_CHOICES)
async def destroy(interaction: discord.Interaction, env: str):
    await interaction.response.defer(thinking=True)

    resp = requests.post(
        f"{GITHUB_API}/actions/workflows/terraform-destroy.yml/dispatches",
        headers=HEADERS,
        json={"ref": env, "inputs": {"environment": env}},
    )

    if resp.status_code == 204:
        await interaction.followup.send(
            f"💥 Destruction lancée sur **{env}** !\n"
            f"Suivi : https://github.com/{GITHUB_REPO}/actions"
        )
    else:
        await interaction.followup.send(
            f"❌ Erreur GitHub API `{resp.status_code}` : {resp.text}"
        )


@client.tree.command(name="status", description="Affiche le statut des 3 derniers workflows")
async def status(interaction: discord.Interaction):
    await interaction.response.defer(thinking=True)

    resp = requests.get(f"{GITHUB_API}/actions/runs?per_page=3", headers=HEADERS)

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
