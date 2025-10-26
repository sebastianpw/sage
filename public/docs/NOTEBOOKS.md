# Notebooks Module - User Guide

## What is This?

This module is **one of the available options for SAGE's image generation system**. It lets you manage and run Stable Diffusion jupyter notebooks on Kaggle's free cloud servers with GPU access, and connects them securely to your SAGE application through Zrok tunnels.
SAGE comes with local preconfigured fully functional jupyter notebooks already!

**SAGE's Image Generation Options:**
- **Kaggle Notebooks** (this module) - Free GPU-powered Stable Diffusion with full model control (tgis module)
- **Pollinations** - External free API for quick image generation, no setup for txt2img, needs free API key for img2img
- **Freepik** — free external API (for Composite nanobana feature, needs API key)

You can switch between these options at any time using **Dashboard → Scheduler → "switch genframe to..."**

---

## Quick Start (TL;DR)

1. Add Kaggle + Zrok tokens in **🔑 API Configuration**
2. In **Notebooks**: pick a model and click
**▶️ Push to Kaggle**  (for 💻 Local only notebooks) or **🔄 Sync & Run** when your notebook exists already remote
3. Wait for the Zrok tunnel to appear in the **🌐 Zrok** card
4. In **Dashboard → Scheduler**:
   - Run **switch genframe to ...** [jupyter OR jupyter_lcm]
   - Run **zup 🚇 update genframes zrok tunnel url**
5. Set active style in **Floatool → 🎨 Styles** (match model)
6. Generate images in SAGE
7. Make sure to ALWAYS manually stop notebooks in your kaggle account "View active events" on kaggle.com (after a complete image generation session with multiple image generations) to only use GPU hours when you are really generating images

---


## The Big Picture: How SAGE Generates Images

```
Your SAGE UI → Selected Service → Generated Images
              ↓
     ┌────────┴────────┐
     │                 │
  Notebooks      External APIs
  (via Zrok)   (Pollinations/Freepik)
     │
  GPU on Kaggle
```

**With Notebooks (this module):**

1. You use SAGE's interface to describe the image you want
2. SAGE sends the request through a secure Zrok tunnel
3. A Kaggle notebook with GPU processes your request using Stable Diffusion
4. The generated image comes back through the tunnel to SAGE
5. You see your image - all without needing your own GPU!

**With External APIs:**

1. You use SAGE's interface to describe the image you want
2. SAGE sends the request directly to Pollinations or Freepik
3. Their servers process your request
4. The generated image comes back to SAGE
5. You see your image!

## Why Use Notebooks Instead of External APIs?

**Advantages of Notebooks:**
- Full control over which Stable Diffusion model to use
- Access to specialized models (anime, photorealistic, artistic styles)
- ControlNet support for precise image control
- Free GPU access (within Kaggle's weekly quota)
- Your own private instance

**Advantages of External APIs:**
- No setup required
- Instant availability (no waiting for notebook startup)
- No GPU quota management
- No tunnel configuration needed

**Use notebooks when you want specific models and control. Use external APIs when you want convenience and speed.**

## Why Use This?

- **Multiple Generation Options**: Choose between notebooks (full control) or external APIs (convenience)
- **Free GPU Access**: Generate images without expensive hardware (when using notebooks)
- **Cloud Computing**: All processing happens on remote servers, not your device
- **Multiple Models**: Access different Stable Diffusion models for different styles
- **Easy Management**: Pull, edit, and push notebooks with just a few clicks
- **Flexible Setup**: Switch between notebooks and external APIs anytime
- **Secure Tunneling**: Private connections to your notebooks (when using Zrok)

---

## Managing GPU Usage

### Important: Stop Notebooks When Not in Use

**Kaggle provides limited free GPU hours per week**. To conserve your quota:

1. **Go to your Kaggle account** at [kaggle.com](https://www.kaggle.com)
2. **Select "View active events"**
3. **Stop any running notebooks** when you're done generating images

**Best Practice**: Always manually stop notebooks in Kaggle when not actively generating images. Don't rely on auto-timeout.

### Checking Your GPU Usage

- Log in to Kaggle
- Go to your profile
- Check your weekly GPU quota usage
- Plan accordingly to avoid running out mid-project

---

## Connecting SAGE to Your Notebook

### Understanding the Connection Requirements

SAGE's Scheduler is the bridge between your notebooks and image generation. Two settings must be configured:

1. **Genframe Service**: Which image generation method to use
2. **Zrok Tunnel URL**: The connection address to your active notebook

**These do NOT update automatically** - you must manually configure them via Dashboard → Scheduler.

### Setting the Genframe Service

**What is "genframe"?**  
Genframe is SAGE's term for the image generation backend. SAGE supports multiple backends that you can switch between:

- **notebooks [jupyter OR jupyter_lcm]**: Your Kaggle notebooks via Zrok tunnel (full model control, free GPU)
- **pollinations**: External free API (instant, no setup, limited models)
- **freepik**: External paid API (advanced features, requires API key)

**When to set it:**
- First time using any generation service
- When switching between notebooks and external APIs
- When switching between different external APIs
- If image generation suddenly stops working

**How to set it:**
1. Go to **Dashboard → Scheduler**
2. Find the appropriate entry:
   - **"sw 🌠 Switch to genframe API: pollinations, freepik, jupyter, jupyter_lcm"** and fill red box (case-sensitive - no spaces allowed)
   - Red box for Kaggle notebooks: `jupyter` OR `jupyter_lcm`
   - Red box for Pollinations API: `pollinations`
   - Red box for Freepik API: `freepik`
3. Click **▶ [play button]** afterwards
4. Confirmation can be viewed in Dashboard > Log > select latest log out file

**Important**: You can only use one genframe service at a time. Switching is instant and takes effect immediately. Usually you do NOT want to run this during an image generation with multiple images

### Updating the Zrok Tunnel URL

**Why is this needed?**  
Each time a notebook starts, it creates a new Zrok tunnel with a unique URL. SAGE needs to know this URL to send image generation requests. This can only be done after the jupyter notebook successfully created the tunnel URL.

**When to update:**
- ✅ After starting a notebook for the first time
- ✅ After restarting an existing notebook
- ✅ After switching to a different model/notebook
- ✅ If SAGE reports connection errors

**How to update:**
1. **Confirm tunnel is active**:
   - Scroll to 🌐 Zrok section in Notebooks module
   - Verify you see an active tunnel listed
   - Click 🔄 Reload to refresh if needed

2. **Navigate to Scheduler**:
   - Go to **Dashboard** in SAGE's main navigation
   - Click **Scheduler**

3. **Find the tunnel update entry**:
   - Look for: **"zup 🚇 update genframes zrok tunnel url"**

4. **Run the update**:
   - Click the **▶ [play button]**
   - Wait a few seconds for confirmation

5. **Done!**: SAGE can now communicate with your notebook

### What Happens Behind the Scenes

When you run the **zup** scheduler entry:
1. The script `bash/zrok_update.sh` executes
2. It queries the Zrok API for active tunnels
3. It retrieves the current tunnel URL
4. It updates SAGE's configuration file
5. Image generation requests now route to your notebook

---

## Available Stable Diffusion Models

SAGE currently provides these pre-configured notebooks:

### 1. Counterfeit-V3 (SD 1.5 based) with optional ControlNet

**ATTENTION: ControlNet is currently not available but in dev: already supported by notebooks and SAGE UI but not yet implemented in genframe**

- **Supports**: Text-to-Image (txt2img) and Image-to-Image (img2img) with optional ControlNet
- **Special Feature**: ControlNet support for guided image generation
- **Best For**: Anime-style artwork and character generation
- **Special Feature**: Use reference images to guide pose, composition, and structure                           - **Best For**: Precise control over character poses and scene composition
- **Speed**: Medium inference time

### 2. SDXL DreamShaper with LCM
- **Supports**: Text-to-Image (txt2img) and Image-to-Image (img2img)
- **Special Feature**: Lightning-fast inference using Latent Consistency Models
- **Best For**: Quick image generation with high quality
- **Speed**: Very fast (4-8 steps vs typical 20-50)

**More models coming soon!** Additional notebooks with different styles and capabilities will be rolled out.

** find more in your notebooks view in SAGE **

---

## Getting Started

### Step 1: Get Your Kaggle API Credentials

1. **Create a Kaggle account** (if you don't have one):
   - Go to [kaggle.com](https://www.kaggle.com)
   - Click "Register" and create a free account

2. **Get your API token**:
   - Log in to Kaggle
   - Click on your profile picture (top right)
   - Select "Settings"
   - Scroll down to the "API" section
   - Click "Create New Token"
   - A file called `kaggle.json` will download
   - Open this file with a text editor (Notepad, TextEdit, etc.)
   - You'll see something like:
     ```json
     {
       "username": "yourusername",
       "key": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6"
     }
     ```
   - Keep this window open - you'll need these values

### Step 2: Get Your Zrok API Token

1. **Create a Zrok account**:
   - Go to [zrok.io](https://zrok.io)
   - Sign up for a free account

2. **Get your token**:
   - After logging in, find your API token in your account settings
   - Copy this token - you'll need it in the next step

### Step 3: Configure the Module

1. **Open the Kaggle Notebooks page** in your SAGE interface

2. **Find the "API Configuration" section** (it has a 🔑 key icon)

3. **Fill in the three fields**:
   - **Kaggle Username**: Copy from your `kaggle.json` file (the "username" value)
   - **Kaggle API Key**: Copy from your `kaggle.json` file (the "key" value)
   - **Zrok API Token**: Paste your Zrok token

4. **Click "💾 Save All Credentials"**

5. **Wait for confirmation**: You should see a green success message saying:
   - ✓ All credentials saved successfully
   - Zrok token dataset synced

6. **You're ready!** The Notebooks section will now be enabled

---

## ⚠️ CRITICAL: Dashboard Scheduler Configuration

**Before you can generate images, you MUST configure two scheduler settings.**

** SEE INSTRUCTIONS ABOVE: Connecting SAGE to Your Notebook**

---

## Understanding the Interface

### Notebook Status Badges

- **✓ Synced** (green): Notebook exists both locally and on Kaggle, and they're in sync
- **☁ Remote only** (yellow): Notebook only exists on Kaggle (not downloaded yet)
- **💻 Local only** (blue): Notebook only exists on your system (not uploaded to Kaggle yet)

### Action Buttons

Each notebook has several action buttons:

- **🔄 Sync & Run**: Downloads the latest version from Kaggle, fixes settings, and runs it
- **📊 Status**: Check if your notebook is currently running, queued, or finished
- **📥 Pull**: Download the notebook from Kaggle to your local system
- **▶️ Push**: Upload your local notebook to Kaggle and run it
- **▶️ Push to Kaggle**: Upload a local-only notebook to your Kaggle account

