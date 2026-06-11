# rssTea - RSS Reader and Podcast PWA using Github Actions

rssTea is a lightweight web application that allows you to read RSS feeds and listen to podcasts directly in your browser. It can fetch feeds from a list provided in a text file and generate an RSS feed for easy consumption. This project utilizes PHP for feed parsing and generation. Additionally, it includes a front-end interface for browsing and playing content.

> Check the frontend live on - https://ayt04.github.io/rssTea/

## Features

```md
Read and display RSS feeds from a list in a text file.

Fetch and parse RSS feeds using GitHub Actions.

Generate an RSS feed and podcast audio for browser playback.

Filter posts by channels and type (posts or podcasts).

A beautiful PWA with MediaSession web API support so you can control your podcast from the notification centre.

Discord Integrations: Get real-time, beautifully formatted notifications inside your Discord channel whenever new content is fetched.
```

## How to Use

### Configuration & Deployment

- Fork the repo and edit the feeds.txt file to add your feeds.
- Enable Github pages on your repo and select deploy from "Github Actions".
- Access your personalised RSS Reader and Podcast Player on https://yourusername.github.io/rssTea

## Setting up Discord Notifications

rssTea can send rich-media updates directly to your Discord server whenever a new podcast episode or video is added to your subscriptions!

- Open your Discord Server Settings -> Integrations -> Webhooks.
- Click Create Webhook, select the target channel, and click Copy Webhook URL.
- Go to your forked repository on GitHub.
- Open the Settings tab -> Secrets and variables -> Actions on the left menu.
- Click New repository secret.
- Set the Name to DISCORD_WEBHOOK and paste your copied Webhook URL into the Value.
- Click Add secret.

> The workflow will now automatically alert you and your community on updates or system failures!

### This project is available under the GNU GPL License.

Feel free to contribute to this project and make it even better! If you encounter any issues or have suggestions for improvements, please open an issue or create a pull request.

### Thank you for using rssTea! Happy browsing and listening! 🍵🎧