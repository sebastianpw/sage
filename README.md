# SAGE — Storyboard Animation Generation Environment

[![MIT License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Stable Diffusion](https://img.shields.io/badge/Stable_Diffusion-XL-blue?logo=stablediffusion)](https://stability.ai/stablediffusion)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php)](https://www.php.net/)
[![MariaDB](https://img.shields.io/badge/MariaDB-10.11-blue?logo=mariadb)](https://mariadb.org/)
[![Runs on Termux](https://img.shields.io/badge/Termux-Android-green?logo=android)](https://termux.dev/)
[![Debian](https://img.shields.io/badge/Debian-Linux-red?logo=debian)](https://www.debian.org/)


> Open-source multimedia AI orchestration platform for creative storytelling — runs fully on Android via Termux

**SAGE** (Storyboard Animation Generation Environment) is an open-source multimedia AI orchestration and automation platform. It merges creative generation, development assistance, and intelligent orchestration under one cohesive system — fully runnable in Termux on Android.


---

## ⚡ Quick Summary

Transform your Android device into a complete AI movie storyboard production studio:
- Generate storyboards with Stable Diffusion
- Orchestrate batch workflows via PHP + bash automation
- Manage projects through intuitive web GUI

---

## 🚀 Core Features

### 🎨 AI Content Generation
- **Stable Diffusion XL** with txt2img/img2img pipelines
- Supports **ControlNet**, **LCM (Latent Conditioning Mixing)** for multi-image consistency and VRAM efficiency
- Currently in dev: **IP-Adapter** and **AnimateDiff** support
- **Multi-image conditioning** including multi-source img2img
- Integration with **Pollinations.ai**, **Groq**, **Colab**, and **Kaggle**

### 📚 Content Management
- **Multi-Gallery System**: 
  - SwiperJS for smooth mobile-friendly swipe navigation
  - PhotoSwipe for zoomable, touch-enabled lightbox
  - ScrollMagic for infinity scroll-driven animations
  - 3D Gallery Viewer for interactive model inspection
  - Slideshow Mode for auto-play sequences
  - Native Video Player for MP4/WebM files
- **Simple CMS**: WYSIWYG editor, dynamic pages, template system with database integration
- **Storyboard View**: Sequential frame timeline for project visualization

### 🤖 AI-Assisted Workflow
- **GPT Conversations**: Automated ChatGPT conversation.json importer with syntax highlighting, timestamps, and full-text search
- **SAGE TODOs**: AI-powered intelligent ticket system with smart summarization and priority suggestions
- **Codeboard**: Code analysis and review with cross-file context integration
- **AI Chat & Helpdesk**: Multi-API, multi-model chat UI with prompt templates and model profiles
- **AI JSON based generator**: AI-powered Brainstorming tool

### 🔧 Automation & Tools
- **Batch Importers**: Easily add frames, prompts, datasets, and multi-image references
- **Image Tools**: Normalize, squarify, upscale, and prepare inputs (imglab tools)
- **PHP Heartbeat Scheduler** with bash orchestration scripts
- **Tabs & Bookmarks System**: Dynamic manager with browser/JSON import
- **Multi-endpoint Orchestration**: Webhooks, cloud tunnels (cloudflared/ngrok/zrok), notebook bridges

---

## 🎯 Use Cases

- **Storyboard Artists**: Generate and organize visual narrative sequences
- **Game Developers**: Create concept art and character references
- **Content Creators**: Automate social media visual content pipelines
- **Developers**: Mobile-first AI experimentation and prototyping
- **Writers**: Visual accompaniment for story development

---

## 🧩 Tech Stack

| Layer | Technology |
|-------|------------|
| **Backend** | PHP 8.3, Symfony Components, Composer |
| **Frontend** | HTML5, JavaScript, jQuery, SwiperJS, PhotoSwipe, ScrollMagic |
| **Database** | MariaDB 10.x / MySQL 8.x |
| **AI Models** | Stable Diffusion XL, ControlNet, IP-Adapter, AnimateDiff, LCM |
| **Deployment** | Termux (Android), Debian (proot), Docker |
| **Orchestration** | PHP scheduler + Bash scripts |
| **APIs** | OpenAI, Pollinations.ai, custom endpoints |

---

## 📦 Installation

### Prerequisites
- **Termux** (Android) or **Linux/macOS**
- PHP 8.3+
- MariaDB/MySQL
- Composer
- Git

### Termux Quick Start

```bash
# Update packages
pkg update && pkg upgrade -y

# Install dependencies
pkg install php mariadb composer git nginx ffmpeg jq -y

# Clone repository
git clone https://github.com/petersebring/sage.git
cd sage

# Install PHP dependencies
composer install

# Setup database
./rollout/init_db.sh

# Start services
./bash/restart_servers.sh
```

Visit **http://localhost:8080/setup.php** to access the web interface.

### Docker Installation (Optional)

```bash
docker-compose up -d
```


### Secrets Setup

Before running the project, you need to provide your API tokens and credentials. The repository contains `.todo` placeholder files — you must replace them with your actual keys.
> **Note:** All API tokens are optional; however, certain features require them: image-to-image generation needs the Pollinations token (or you manually add a kaggle/colab SD service API), GROQ chat models need the Groq token, and Google login requires a valid Google OAuth client secret.


#### 1. Pollinations AI Token

File:

```
./token/.pollinationsaitoken
```

- Copy `.pollinationsaitoken.todo` → `.pollinationsaitoken`  
- Put only the token value in the file (no extra spaces or newlines).  

#### 2. FreeImage API Key

File:

```
./token/.freeimage_key
```

- Copy `.freeimage_key.todo` → `.freeimage_key`  
- Put only your FreeImage API key in the file.  

#### 3. Groq API Key

File:

```
./token/.groq_api_key
```

- Copy `.groq_api_key.todo` → `.groq_api_key`  
- Insert your Groq API key.  

#### 4. Google OAuth Client Secret

File:

```
./token/client_secret_google_oauth.json
```

- Copy `client_secret_google_oauth.json.todo` → `client_secret_google_oauth.json`  
- Replace with the full JSON file that Google provides when configuring your OAuth client.  

> **Important:** Do **not** commit these files with real keys. They are local secrets and should remain outside version control.

### Kaggle and Google Colab Notebooks

Some advanced AI image generation models and workflows use Kaggle and Google Colab notebooks (free tier). These notebooks are **not yet included in the repository** but will be provided asap in ./notebooks. They can be used for alternative img2txt and img2img generations. Controlnet and upscale has these as a dependency.



### Troubleshooting

**Common Issues:**
- *Server fails*: Run ./bash/restart_servers.sh
- *Database connection failed*: Check MariaDB service is running with `mysqld`
- *Composer errors*: Ensure PHP 8.3+ is installed with `php -v`

See [docs/INSTALL.md](docs/INSTALL.md) for detailed setup instructions.

---

## 🤝 Contributing

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct and pull request process.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## ⚠️ Known Limitations

- LCM requires significant VRAM (8GB+ recommended for larger models)
- Termux performance varies by device specifications
- Some features require internet connectivity for cloud APIs

---

## 🌌 Project Vision

SAGE aims to unify **AI generation**, **automation**, and **storytelling** into a single semantic framework. The goal is to empower creators and developers to orchestrate complex multimedia narratives with reproducible, auditable pipelines — directly from mobile devices when needed.

---

## 🙏 Acknowledgments

- pollinations.ai
- Black Forest Labs
- Stable Diffusion community
- Symfony framework contributors
- Termux development team
- All open-source libraries utilized in this project
- OpenAI and Anthropic for integration support

---

## 📜 License

This project is licensed under the **MIT License** — see the [LICENSE](LICENSE) file for details.

© 2025 **Sebastian Peter Wolbring (Peter Sebring)**

---

## 💬 Contact / Author

**Peter Sebring**  
Musician, producer and AI enthusiast  
📍 Based in Cologne, Germany  
🎵 Exploring the intersection of art, automation, and anime storytelling

---

## ⭐ Support

If you find SAGE useful, please consider:
- Starring this repository
- Sharing it with others
- Contributing to the codebase
- Reporting bugs and suggesting features

---

**Made with ❤️ for the creative coding community**
