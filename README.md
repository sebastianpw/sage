# SAGE 2.0 BETA

[![Built with Pollinations](https://img.shields.io/badge/Built%20with-Pollinations-8a2be2?style=for-the-badge&logo=data:image/png;base64,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&logoColor=white&labelColor=6a0dad)](https://pollinations.ai)

[![MIT License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Stable Diffusion](https://img.shields.io/badge/Stable_Diffusion-XL-blue?logo=stablediffusion)](https://stability.ai/stablediffusion)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php)](https://www.php.net/)
[![MariaDB](https://img.shields.io/badge/MariaDB-10.11-blue?logo=mariadb)](https://mariadb.org/)
[![Runs on Termux](https://img.shields.io/badge/Termux-Android-green?logo=android)](https://termux.dev/)
[![Debian](https://img.shields.io/badge/Debian-Linux-red?logo=debian)](https://www.debian.org/)
[![Python 3.8+](https://img.shields.io/badge/Python-3.8%2B-3776AB?logo=python&logoColor=white)](https://www.python.org/)
[![FastAPI](https://img.shields.io/badge/FastAPI-009688?logo=fastapi&logoColor=white)](https://fastapi.tiangolo.com/)

> Open-source multimedia AI orchestration platform for creative storytelling — runs fully on Android via Termux

**SAGE** (Storyboard Animation Generation Environment) is an open-source multimedia AI orchestration and automation platform. It merges creative generation, development assistance, and intelligent orchestration under one cohesive system — fully runnable in Termux on Android.

---

## ⚡ Quick Summary

**THIS IS A BETA RELEASE. Only minor bugfixes left!**
TRY AT YOUR OWN RISK.

**Install in GitHub Codespaces terminal:**
```bash
/var/www/sage/rollout/init_db.sh
# (Only first time)
```

**To restart or if servers didn't start:**
```bash
/var/www/sage/bash/restart_servers.sh
```

*Use cloudflared URL to access SAGE web UI.*

**PLEASE MAKE SURE TO PROVIDE YOUR AI PROVIDER TOKEN IN SAGE DASHBOARD FOR EXAMPLE POLLINATIONS.AI**

**P.S.: For ChromaDB ingestion use:**
```bash
/var/www/sage/bash/ingest_sketches.sh
/var/www/sage/bash/ingest_docs.sh
/var/www/sage/bash/ingest_kg.sh
```

Please like, share, subscribe...  
🌐 [petersebring.com](https://petersebring.com)

---

## 🧠 About SAGE (Story Architecture & Generative Engine)

**SAGE AI** is a bespoke, end-to-end production platform designed to power the creation of *The Anima Chronicles* — a 60-episode sci-fi/fantasy animated series. Built for the modern solo-showrunner, SAGE operates entirely on a mobile-first, self-hosted Android/Termux stack (PHP/MariaDB + Python FastAPI) and unifies story architecture, generative AI asset creation, video editing, and audio production into a single, seamless pipeline.

Rather than bridging disparate software, SAGE integrates the entire creative lifecycle: from prompting a world-building Knowledge Graph, to assembling beats in a virtual writer's room, generating thousands of frames, and bouncing composited video and multitrack audio natively in the browser.

## 🚀 Core Subsystems

### 🏛️ Story Architecture & Lore
* **Writers Room Forge:** A structured, conversational AI environment to stress-test 60-episode story architecture, map consequences of narrative decisions, and track Chekhov's Gun debts across seasons.
* **Knowledge Graph:** A curated, Markdown-rich lore intelligence layer. Interconnected entities, relationships, and concepts are automatically embedded into a ChromaDB vector store, enabling deep semantic search and context injection directly into AI prompts.
* **KG Story Bible:** A read-oriented lore browser for the Knowledge Graph. Presents the entire curated KG as a grid of expandable cards and collapsible sub-folders, allowing the showrunner to quickly navigate, deep-link, and download JSON/Markdown exports of curated lore.
* **WordNet Admin:** A lexical database browser providing direct access to Princeton WordNet. Explores synsets, hypernyms, and usage examples to inform language modeling and prompt construction within the lore and scripting layers.
* **Dictionary & Lemma Manager:** A text corpus ingestion and vocabulary library system. Extracts, normalizes, and lemmatizes raw texts (PDFs/TXTs) into curated word banks to inform the language and tone of generated content.
* **Bloom Oracle:** A probabilistic vocabulary hint system. Samples lemmas from SAGE dictionaries and encodes them into space-efficient Bloom filters to provide lightweight semantic guides for AI generation prompts without bloating context windows.
* **Documentation & MD Curator System:** An integrated knowledge-management layer providing a full lifecycle pipeline for Markdown documents. It bridges raw narrative writing and downstream vector search by chunking documents, calling AI generators to extract lore data, and aggregating them into the Knowledge Graph and Story Bible.
* **Fuzz Forge:** A lore concept consolidation pipeline. Automatically extracts noun-phrase mentions from across the database and submits them to a TF-IDF sparse-clustering PyAPI job to identify fuzzy duplicates, allowing the showrunner to canonize fragmented concepts into the Knowledge Graph.
* **Lore Explorer & Story Bible:** A unified interface for navigating and exporting aggregated lore. Pairs a semantic vector search engine with an interactive Story Bible document renderer to instantly locate any character, location, or thematic element across thousands of pages of source material.

### 🎬 Narrative & Pre-Visualization
* **PLUSH (PLot Us Story Highlights):** A dedicated, non-destructive drafting board for rapid beat assembly. Organize free-text highlight blocks, tag them with polymorphic entities, and color-code emotional beats.
* **Showrunner (Auto-Narrative Lab):** An algorithmic editorial studio. Manually drag sketches into sequence timelines, or unleash an AI Director to autonomously chain coherent shots based on cinematic logic, visual style, and vector-swept context.
* **Production Boards:** A visual curation and cross-reference workspace. Acts as a unified pinboard where Map Runs, Storyboards, Documents, Fuzz Candidates, and Knowledge Graph nodes can be gathered, reordered, and reviewed in a single hierarchical folder tree.
* **Semantic Sketch Viewer & Sketch Match:** A visual asset discovery and lore-bridging system. Explores generated sketches via MiniLM semantic text search and CLIP visual similarity, matching visual assets directly to contextually resonant lore documents in ChromaDB.
* **Storyboards & SbCut:** A visual frame sequencing and pre-visualization workspace. Assemble generated frames into ordered, categorized boards, manage bulk imports, visually split sequences, and seamlessly clone visual timelines directly into the narrative engine.
* **Paginated Storyboards:** A high-performance storyboard browser and editor. Manages bulk frame selection, cross-board frame copying, and lazy-loaded physical file propagation across large-scale production boards.
* **Storyboard Data Mining:** A graph-analytics engine for the visual production layer. Treats the relationship between rendered frames and storyboards as a bipartite network, exposing six distinct graph-theory lenses (Degree, Centrality, Clustering, Communities, Co-occurrence, Orphans) to identify visual continuity hubs and redundant assets across the production.
* **Storyboards Chain View:** A visual continuity browser for assembled storyboards. Presents frames as horizontally scrollable card chains, surfacing generative lineage, AI curation analysis, and prompt ingredients for rapid QA.
* **Story Timelines:** Full-screen chronological visualizations powered by TimelineJS. Review both PLUSH story drafts and production-ready visual sequences on the same temporal axis.
* **Curated Sketches Analysis (Scene Curation):** A scannable curation interface for reviewing AI-scored sketch assets. Consolidates generated frames with their underlying narrative function, emotional tone, and production readiness scores to enable rapid QA.
* **Narrative Sequence Analysis:** A deep-dive episode document viewer presenting the full AI-generated narrative analysis of a single sequence as a structured reference, uniting act structure, open tensions, and per-beat analyst data.
* **AnimeJSeq Viewer:** SAGE's cinematic playback interface for narrative sequences. A full-screen, scroll-driven gallery that presents generated sequences as immersive, animated visual experiences, complete with ambient blurred backgrounds and self-contained ZIP export modes.

### 🎨 Generative Asset Production
* **Entity Gallery System:** A platform-wide architectural convention managing the CRUD, visual browsing, and batch-run review for every visual and narrative entity in the pipeline (Characters, Factions, Vehicles, Spawns, etc.), complete with PhotoSwipe galleries and GearMenu integrations.
* **Entity Viewer:** A universal asset gallery and inspection interface. Provides a paginated, multi-mode browser for viewing frames across all major production entity types, grouped by entity or by generation batch (Map Run).
* **LocaHub:** A unified location discovery and consolidation browser. Surfaces location entities scattered across five distinct data sources (canonical tables, Fuzz candidates, KG nodes, AG nodes, sketch ranges) and presents them in a single browsable interface for batch deduplication and migration into the core entity layer.
* **Mass Import Tools:** SAGE's bulk ingestion layer. Provides a suite of dedicated views and drag-and-drop utilities for moving large quantities of external assets (images to spawns, audio to dialogue, video to animatics) into the relational production pipeline in a single operation.
* **Scene Kitchen:** A generative scene assembly studio. Drop modular creative building blocks (templates, style profiles, characters, lore) into a central "Pot," enforce character visual continuity, and synthesize production-ready AI image prompts.
* **Character Continuity System:** An AI-driven pipeline for enforcing visual character consistency. Fuses precise physical descriptions from character records with cinematic scene prompts to ensure reliable visual translation across generated frames.
* **Rapid Forge:** An automated batch generation factory. Processes predefined narrative and visual scenarios through a two-pass AI pipeline, producing named and described Sketch records at scale to solve cold-start pre-production.
* **Character Pose Import System:** A bulk combinatorial generation tool for seeding core visual entity libraries. Computes the full Cartesian product of characters, camera angles, perspectives, and poses, updating the database transactionally to feed downstream image generation queues.
* **Lore API Processor (The Curator's Studio):** An AI showcase generation workbench for curated lore. Extracts structured semantic context for world entities and uses it as grounding data to generate structured Markdown image prompts, publishing them directly into the CLI image-generation pipeline.
* **KG Rapid API & Sketches Processor:** AI showcase generation workbenches operating directly on curated lore and Knowledge Graph tables. Assembles semantic context from entity relationships to produce structured visual prompt showcases at scale.
* **Generator Forge:** A unified registry and execution UI for AI content generators. Encapsulates system roles, schemas, and Oracle Bloom creative seeding into reusable prompt units that power automated analysis pipelines platform-wide.
* **Filter Forge API:** SAGE's unified frame and entity retrieval layer. Resolves complex, multi-domain intersections (Fuzz candidates, Lore docs, KG nodes, Vector text) into coherent, paginated result sets for use across the entire platform.
* **Tagging Ecosystem (Taggeranger, Frames/Videos Taggers & Forwarders):** A comprehensive suite of manual, semi-automated, and bulk-propagation tagging interfaces. Utilizes ChromaDB vector scoring and OpenCV vision analysis to propose tags, allowing human-in-the-loop curation before propagating tags bidirectionally across sketches, frames, animatics, and videos.
* **Videosmatcher:** A semantic reconstruction tool for batch-imported video assets. Runs CLIP visual similarity searches to automatically match and link raw video thumbnails to their origin sketches in the database.
* **Enhanimatics:** The central workstation for reviewing AI-generated frames, composing multi-step image-to-image enhancement instructions, and routing selected assets to the animation pipeline.
* **Enhanistobo & Exposanimaticism:** Specialized entity-focused asset browsers. Enhanistobo provides a storyboard-driven interface for batching frames into the animatic pipeline, while Exposanimaticism offers a dedicated character variant browser for navigating the rendered frame library across Poses, Expressions, and Anima Poses.
* **ScrollMagic Rating Viewer:** An infinite-scroll image gallery for browsing, filtering, and rating the frames asset library. Solves the production challenge of navigating tens of thousands of generated frames without pagination friction using a custom virtual scroll buffer.
* **Sketch & Video Regenerators:** Batch re-queuing interfaces designed to identify and flag previously generated frames and videos for regeneration without re-running a full pipeline pass, preserving the original run record while queuing only the assets that need to be redone.

### 🎞️ Editorial & Compositing
* **Editorial System:** A full-stack pipeline to assemble physical video files into a Series → Season → Episode → Scene hierarchy. Includes Paradigm B, an inline dialogue spotting editor for frame-accurate script-to-audio sync.
* **Video Admin:** SAGE's primary video asset management interface. A paginated grid browser for bulk video browsing, metadata editing, playlist assignment, and initiating background-removal pipeline jobs.
* **VidBat Review:** A mobile-first video QA and organization workspace. Rapidly triage generated video clips, flag keepers, assign batches to the hierarchical story tree, and queue background-removal jobs across multiple filter dimensions.
* **VedTriccs:** A multi-track video timeline editor and compositor. Sequence clips and inject cinematic transition effects (from a vocabulary of 60+ styles) rendered natively via OpenCV and FFmpeg on the Python backend.
* **MuviTriccs Engine:** The dedicated video transition compositor and Python/OpenCV render engine backing VedTriccs. Executes parametric, frame-by-frame visual transitions (incorporating optical flow and depth mapping) natively without external NLE software.
* **MultiVid 2.5D Multiplane Compositor:** A parallax animation editor that combines still frames and video clips into layered, camera-animated compositions simulating cinematic depth. Supports instant browser-based WebM recording or offline PyAPI FFmpeg rendering.
* **Motion Editor:** A real-time 3D scene compositor and camera direction tool built on Three.js. Assembles a live WebGL stage from animatic assets (background cylinders, plane-mapped videos, GLTF models), allowing precise spatial control, camera physics, and live WebM/MP4 recording with full non-destructive telemetry replay.
* **Babylon Viewer:** An interactive 3D model inspection tool built on the Babylon.js WebGL engine. Enables direct in-browser inspection of `.glb`/`.gltf` assets with full orbit, pan, zoom, and roll camera controls, exposing a snapshot-capture pipeline so reviewed frames can be saved back to the server.

### 🎧 Publishing & Audience Engagement
* **SAGE DAW:** A browser-based multitrack Digital Audio Workstation. Instantly pre-populate timelines with shot-specific audio stems, apply non-destructive volume envelopes and Tone.js FX chains, and bounce mixed WAV files directly into the SAGE database.
* **Cinemagic Editor & NarSeq:** Public-pipeline authoring tools for sequence restructuring, frame cycling, and multilingual overlay text generation.
* **Cinemagic Hub:** A magazine-style publishing engine. Transforms AI-generated narrative sequences into polished, web-ready reading experiences with cinematic scroll-layouts, PDF generation, and automated rollout to GitHub Pages.
* **Content Hub:** A social media command center and full-stack publishing system. Enriches production assets with metadata and templated layouts, exporting them as self-contained static HTML files and rolling them out directly to a GitHub repository via automated git-sync.
* **Mail Hub:** A built-in email marketing and newsletter dispatch system. Manages the full subscriber lifecycle with a zero-PII architecture, integrating Brevo API and raw SMTP drivers to schedule, template, and deliver campaigns directly to audiences.
* **Pytoon Webtoon Rollout Kit:** A webtoon packaging and export station. Transforms production assets into publication-ready webtoon formats via a pixel-precise Cover Compositor and an asynchronous PDF-to-Pages rasterization pipeline backed by the PyAPI.

### ⚙️ Infrastructure & Operations
* **PyAPI Core Architecture:** SAGE's master Python FastAPI microservice orchestrator. Runs localized NLP, computer vision, and heavy-duty rendering. Features aggressive dynamic service registries, C-level threading locks (to prevent mobile RAM crashes), asynchronous background task queueing, and fallback zero-dependency mathematical algorithms.
* **Kaggle Notebooks:** A remote compute orchestration layer. Manages, syncs, and launches Jupyter notebooks on Kaggle's cloud GPU infrastructure directly from the dashboard, injecting Zrok reverse-proxy tokens to bridge local and remote pipelines.
* **Production Stats Dashboard:** A real-time signal intelligence panel. Tracks generation coverage, detects unmapped entities, and visualizes the complete linkage chain from scripts through storyboards and editorial video sequences using animated SVG gauges and cluster analysis.
* **Queue Viewer:** A real-time production job monitor and control panel. Surfaces every pending, processing, and completed generation task, allowing the operator to dynamically reprioritize jobs and override AI provider models on the fly.
* **Scheduler Forge:** A self-contained, database-driven daemon management system built to replace cron in the Android/Termux environment. Manages long-running scripts, enforces mutex locks for concurrency, and streams real-time execution logs.
* **Scheduler Log Viewer:** A real-time log monitoring tool streaming the output of bash worker scripts directly to the browser with smart auto-scrolling and pause controls.
* **CLI Forge Hub:** A unified command-and-control interface for dispatching background jobs. Configures JSON payloads for 10 distinct CLI pipelines (sketch generation, lore extraction, GitHub sync) via a mobile-first queue monitor.
* **SAGE Documentation Hub:** A unified, self-hosted documentation browser that perfectly mirrors the platform's production dashboard, providing zero-friction, fullscreen access to module architectures and system blueprints.
* **SAGE TODOs:** A lightweight, priority-ordered developer backlog embedded directly in the platform. Features drag-and-drop reordering and an AI analysis layer that suggests automated priority adjustments as the platform evolves.
* **Entity Form & Frame Viewer:** SAGE's universal record editor. Dynamically adapts to any entity type in the production database (from characters to composites), aggregating editable fields, generated media, and cross-system lore references into a single, unified inspection hub.
* **Database Tool (`dbtool`):** A built-in, mobile-accessible MariaDB administration interface. Allows direct inspection, schema evolution, SQL querying, and full database dumping without leaving the SAGE environment.
* **Database Migration Manager:** A schema synchronization and version upgrade tool. Performs deep schema introspection using `information_schema`, generates safe, prioritized migration SQL, validates it against SAGE's AI provider, and executes it within a transactional boundary with rollback support.
* **SketchUp Export:** A context-rich JSON/SQL data packaging tool. Extracts coherent subsets of production content for use in AI inference pipelines or cross-environment migrations.
* **Sketch Migration Forge:** A cross-instance portability system. Losslessly exports and imports sketch collections (with metadata, analysis scores, and image binaries) between separate SAGE deployments using zip-bundled SQL dumps.
* **Chroma Collections Admin & ChromaTool:** Vector database administration interfaces. Manage the registry of ChromaDB collection definitions and provide direct CRUD access to the embeddings that power SAGE's semantic search.
* **Backup Forge:** The platform's infrastructure protection layer. A browser-based control panel for configuring, triggering, and monitoring the full backup pipeline, pushing media archives, database dumps, and codebase snapshots to remote destinations over SCP with SHA-256 verification.
* **Recipe Forge:** A code-dump provenance and registry browser. Tracks every invocation of the platform's context-bundling scripts, recording exact file inclusions, content hashes, and CLI reproduction commands to ensure any AI prompt session can be precisely audited and recreated.
* **Forge Tool, Clipboard & Control Deck:** A persistent, platform-wide floating action button (FAB) injecting a cross-module productivity layer. Includes a globally scoped clipboard manager and a curated launcher grid for triggering scheduled tasks with a single tap.

## 🛠️ Tech Stack & Architecture

SAGE is built for aggressive portability and offline-first resilience:
* **Frontend:** Vanilla JS, Forge UI (Custom Design System: Space Mono + Syne, Dark/Light modes), SortableJS, WaveSurfer.js, Swiper.js, Sigma.js, Three.js, jsTree.
* **Backend:** PHP 8.3+ + MariaDB 10.11+ (Content & Logic Layer), Nginx.
* **Inference / PyAPI (Python FastAPI):**
  * **Dynamic Routing:** Instantly morphs from a lightweight mobile server into a full-scale AI render farm using `serv.conf.json`.
  * **Video/Media:** Native OpenCV/FFmpeg pipelines (MuviTriccs, MultiVid, VED) and automated lip-syncing (LipLab).
  * **AI/Inference:** Background removal (u2net/birefnet), ControlNet maps, Voice Cloning (so-vits-svc, Qwen3-TTS), and local Ollama daemon routing.
  * **RAG/Embeddings:** ChromaDB lifecycle management, fast embedding generation (MiniLM/CLIP), and O(N) TF-IDF sparse clustering for the Fuzz Forge.
* **AI Integration:** Unified provider layer routing to Pollinations, Groq, Cerebras, Google Gemini, Mistral, and Cohere.

## 📜 Notice

*SAGE AI and The Anima Chronicles are proprietary creations. This repository represents the architectural tooling built to execute the series. All integrated lore, characters, and generated assets remain the exclusive intellectual property of the creator.*

---

## 🙏 Acknowledgments

- pollinations.ai
- kaggle.com
- freepik.com
- groq.com
- Black Forest Labs
- Stable Diffusion community
- Symfony framework contributors
- Termux development team
- Princeton WordNet — for the lexical database used in WordNet integration
- All open-source libraries utilized in this project
- OpenAI and Anthropic for integration support

---

## 📜 License

This project is licensed under the **MIT License** — see the [LICENSE](LICENSE) file for details.

© 2026 **Sebastian Peter Wolbring (Peter Sebring)**

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


