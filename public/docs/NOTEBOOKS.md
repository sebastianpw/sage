# Notebooks Module - User Guide

## What is This?

This module is a central part and one of the options for SAGE's **cloud-based image generation system**. It lets you manage and run Stable Diffusion notebooks on Kaggle's free cloud servers with GPU access, and connects them securely to your SAGE application through Zrok tunnels.

## The Big Picture: How SAGE Generates Images

```
Your SAGE UI → Zrok Tunnel → Kaggle Notebook (GPU) → Stable Diffusion Model
     ↑                                                          ↓
     └──────────────── Generated Images ←─────────────────────┘
```

**Here's what happens:**

1. You use SAGE's interface to describe the image you want
2. SAGE sends the request through a secure Zrok tunnel
3. A Kaggle notebook with GPU processes your request using Stable Diffusion
4. The generated image comes back through the tunnel to SAGE
5. You see your image - all without needing your own GPU!

## Why Use This?

- **Free GPU Access**: Generate images without expensive hardware
- **Cloud Computing**: All processing happens on Kaggle's servers, not your device
- **Multiple Models**: Access different Stable Diffusion models for different styles
- **Easy Management**: Pull, edit, and push notebooks with just a few clicks
- **Automatic Setup**: GPU, internet, and Zrok are automatically configured
- **Secure Tunneling**: Your connection to the notebook is private and secure

---

## Available Stable Diffusion Models

SAGE currently provides these pre-configured notebooks:

### 1. Counterfeit-V3 (SD 1.5 based) with optional ControlNet
- **Supports**: Text-to-Image (txt2img) and Image-to-Image (img2img) with optional advanced control
- **Special Feature**: Use img2img reference image to guide pose, composition, and structure
- **Special Feature**: ControlNet support for map- and skelton-based guided image generation
- **Best For**: Anime-style artwork and character generation
- **Best For**: Precise control over character poses and scene composition
- **Speed**: Medium inference time

### 2. SDXL DreamShaper with LCM
- **Supports**: Text-to-Image (txt2img) and Image-to-Image (img2img)
- **Special Feature**: Lightning-fast inference using Latent Consistency Models
- **Best For**: Quick image generation with high quality
- **Speed**: Very fast (4-8 steps vs typical 20-50)

**More models coming soon!** Additional notebooks with different styles and capabilities will be rolled out.

---

## Understanding the Zrok Connection

### What is Zrok?

Zrok is a secure tunneling service that creates a private connection between your SAGE application and your Kaggle notebooks. Think of it as a private highway that only you can use.

### How to Know Your Notebook is Ready

Look for the **🌐 Zrok** section at the bottom of the page. This shows you the Zrok interface which displays:

- Your Zrok account status
- Active tunnels (connections to running notebooks)
- Tunnel health and activity

**When you see an active tunnel listed** → Your notebook is running and SAGE can generate images!

### The Notebook Lifecycle

1. **You start a notebook** (using 🔄 Sync & Run or ▶️ Push)
2. **Notebook initializes** on Kaggle (downloads model, sets up environment)
3. **Zrok tunnel is created** (final step in the notebook)
4. **Tunnel appears in Zrok interface** → ✅ Ready!
5. **SAGE can now send requests** to generate images

**Important**: The notebook must complete all setup steps before the tunnel appears. This can take 2-10 minutes depending on the model size.

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

---

## Common Tasks

### Starting Image Generation (First Time Setup)

**Goal**: Get a Stable Diffusion model running so SAGE can generate images

1. **Choose your model**: Decide which notebook you want to use (see "Available Stable Diffusion Models" above)
2. **Find it in the list**: Look for the notebook name in the Notebooks section
3. **Click 🔄 Sync & Run**: This downloads and starts the notebook
4. **Wait for initialization**: This takes 2-10 minutes (downloading models, setting up GPU)
5. **Check the Zrok section**: Scroll down to the 🌐 Zrok card
6. **Wait for tunnel to appear**: When you see an active tunnel, you're ready!
7. **Generate images in SAGE**: Go to SAGE's main interface and start creating!

### Checking if Your Notebook is Ready

**Quick Method:**
- Scroll to the **🌐 Zrok** section
- Click **🔄 Reload** to refresh
- If you see an active tunnel → Your notebook is ready
- If no tunnel → The notebook is still starting up

**Detailed Method:**
1. Find your notebook in the list
2. Click **📊 Status**
3. Look for "Running" or "Complete"
4. Then check the Zrok section for the tunnel

### Running a Notebook for the First Time

**If the notebook is on Kaggle:**

1. Find your notebook in the list
2. Click **🔄 Sync & Run**
3. Wait for the confirmation message
4. Monitor the Zrok section for tunnel creation
5. Once tunnel appears → Start generating images!

**If the notebook is only on your computer:**

1. Find your notebook in the list (it will show "💻 Local only")
2. Click **▶️ Push to Kaggle**
3. Confirm the action
4. Wait for initialization (2-10 minutes)
5. Check Zrok section for active tunnel

### Switching Between Models

Want to try a different Stable Diffusion model?

1. **Stop the current notebook** (optional - Kaggle will auto-stop after inactivity)
2. **Start a different notebook** using 🔄 Sync & Run
3. **Wait for the new tunnel** to appear in Zrok
4. **SAGE automatically uses** the new model

You can only have one model running at a time (to conserve GPU quota).

### Restarting a Notebook

If your notebook stopped or you need to restart:

1. Click **🔄 Sync & Run** on the notebook
2. Wait for initialization
3. Check Zrok for the tunnel
4. Resume generating images

### Monitoring the Zrok Interface

The **🌐 Zrok** section shows real-time information about your tunnels:

**What you'll see:**
- **Account status**: Your Zrok account information
- **Active tunnels**: List of currently running connections
- **Tunnel details**: URLs, status, and activity for each tunnel

**Using the interface:**
- Click **🔄 Reload** to refresh the Zrok status
- The interface is scaled down to fit - it's viewing the actual Zrok API dashboard
- Active tunnels mean your notebooks are ready to generate images

**Troubleshooting with Zrok:**
- No tunnels showing? Your notebook is still initializing
- Old tunnels showing? They'll automatically clean up when notebooks stop
- Multiple tunnels? You might have multiple notebooks running (only one needed)

### Downloading a Notebook from Kaggle

1. Find the notebook (it will show "☁ Remote only")
2. Click **📥 Pull**
3. The notebook will be downloaded to your local system
4. You can now edit it locally

### Updating and Re-running a Notebook

**Option A: Update from Kaggle first (recommended)**

1. Click **🔄 Sync & Run**
2. This downloads any changes from Kaggle, then runs the latest version

**Option B: Push your local changes**

1. Edit your notebook locally
2. Click **▶️ Push**
3. Your local version is uploaded and run

### Manually Syncing the Zrok Dataset

If you update your Zrok token:

1. Update the token in the "API Configuration" section
2. Click **🔄 Sync Zrok Dataset**
3. Your new token will be uploaded to Kaggle

---

## What Happens Automatically

When you push or sync a notebook, the system automatically:

1. **Enables GPU**: Your notebook can use Kaggle's GPU for AI models
2. **Enables Internet**: Your notebook can download datasets and packages
3. **Adds Zrok Dataset**: Your Zrok token is available to the notebook for secure tunneling
4. **Fixes Metadata**: Ensures all settings are correct for Kaggle

You don't need to do anything - it just works!

---

## Understanding Metadata

Each notebook has a `kernel-metadata.json` file that tells Kaggle how to run it. This file includes:

- Notebook title and ID
- Whether to enable GPU/Internet
- Which datasets to attach
- Language and kernel type

**Don't worry if your notebook doesn't have this file** - the system will create it automatically when you push to Kaggle!

---

## Troubleshooting

### "Notebook operations are disabled"

**Problem**: You see a lock icon and can't use any notebooks.

**Solution**: Make sure you've entered all three credentials (Kaggle username, Kaggle key, AND Zrok token) in the API Configuration section.

### "Kaggle CLI binary not found"

**Problem**: The Kaggle command-line tool isn't installed.

**Solution**: Contact your system administrator or check the installation documentation.

### "Failed to sync Zrok dataset"

**Problem**: The Zrok token couldn't be uploaded to Kaggle.

**Solution**: 
1. Check that your Kaggle credentials are correct
2. Try clicking "🔄 Sync Zrok Dataset" manually
3. Make sure your Kaggle account is active

### Notebook won't run

**Problem**: You click run but nothing happens or you get errors.

**Solutions**:
1. Click **📊 Status** to see if there's a queue
2. Try **🔄 Sync & Run** to refresh and retry
3. Check that the notebook file (.ipynb) exists in the folder
4. Verify you haven't exceeded Kaggle's weekly GPU quota

### No tunnel appears in Zrok

**Problem**: Notebook is running but no Zrok tunnel shows up.

**Solutions**:
1. **Wait longer**: Initial setup takes 2-10 minutes
2. **Click 🔄 Reload** in the Zrok section to refresh
3. **Check notebook status**: Use 📊 Status to see if it's still starting
4. **Check Kaggle's website**: Visit kaggle.com and check your notebook logs directly
5. **Restart the notebook**: Sometimes a fresh start helps

### SAGE can't generate images

**Problem**: SAGE says it can't connect or times out.

**Solutions**:
1. **Check Zrok section**: Is there an active tunnel?
2. **Verify notebook is running**: Use 📊 Status
3. **Wait for initialization**: Model loading can take several minutes
4. **Check your internet**: Zrok requires active internet connection
5. **Try restarting the notebook**: Click 🔄 Sync & Run again

### Slow image generation

**Problem**: Images take a long time to generate.

**Solutions**:
1. **Use SDXL DreamShaper with LCM**: This is the fastest model
2. **Reduce image size**: Smaller images generate faster
3. **Check Kaggle status**: Their servers might be busy
4. **Use fewer inference steps**: If supported by the model

### "Out of GPU quota"

**Problem**: Kaggle says you've used all your GPU time.

**Solutions**:
1. **Wait for reset**: GPU quota resets weekly
2. **Use CPU notebooks**: Much slower, but free
3. **Upgrade Kaggle account**: Paid tiers offer more GPU time
4. **Optimize usage**: Stop notebooks when not in use

---

## Tips and Best Practices

### ✅ Do This:

- **Always sync before making changes**: Click 🔄 Sync & Run to get the latest version
- **Check status regularly**: Use 📊 Status to see if your notebook finished
- **Keep tokens secure**: Never share your API keys or tokens
- **Use meaningful names**: Name your notebooks clearly so you can find them

### ❌ Avoid This:

- **Don't edit in two places**: If you edit both locally and on Kaggle, you might lose changes
- **Don't share credentials**: Your API keys are personal - don't share them
- **Don't delete metadata files**: The `kernel-metadata.json` file is important

---

## Understanding the Workflow

Here's how SAGE generates your images:

```
1. SAGE Interface
   ↓ (You describe your image)
   
2. This Kaggle Module
   ↓ (Manages notebooks)
   
3. Kaggle Cloud + GPU
   ↓ (Notebook runs Stable Diffusion)
   
4. Zrok Tunnel
   ↓ (Secure connection)
   
5. Generated Image
   ↓ (Returns to SAGE)
   
6. Your Beautiful Image! 🎨
```

**Step by step:**

1. You use SAGE's interface to describe the image you want
2. SAGE sends the request through the Zrok tunnel
3. Your Kaggle notebook receives the request
4. Stable Diffusion processes the request using GPU
5. The image is generated and sent back through the tunnel
6. You see your image in SAGE!

**The beauty of this system:**
- You never need your own GPU
- Everything happens in the cloud
- The connection is secure and private
- You can use multiple different AI models
- It's completely free (within Kaggle's limits)

---

## File Locations

### Where Things Are Stored:

- **Local notebooks**: `notebooks/kaggle/` folder in your project
- **Kaggle credentials**: `token/.kaggle/kaggle.json`
- **Zrok token**: `token/.zrok_api_key`
- **Metadata files**: Inside each notebook folder as `kernel-metadata.json`

---

## Security Notes

### Your Tokens Are Safe

- **Kaggle tokens**: Stored in secure configuration files
- **Zrok token**: Stored locally AND in a **private** Kaggle dataset
- **Private means**: Only you can access it - it's not visible to other Kaggle users
- **Never hardcoded**: Tokens are never written into your notebook code

### The Zrok Dataset

When you save your Zrok token:

1. It's saved locally in `token/.zrok_api_key`
2. A private dataset called `sage-zrok-token` is created on your Kaggle account
3. This dataset contains just one file: `.zrok_api_key`
4. Your notebooks automatically get access to this dataset
5. Your notebook can read the token at runtime (never hardcoded!)

---

## Frequently Asked Questions

**Q: Do I need to pay for Kaggle?**  
A: No! Kaggle offers free GPU time every week. This is completely free to use.

**Q: How much GPU time do I get?**  
A: Kaggle currently provides 30 hours of GPU time per week (subject to change).

**Q: Can others see my notebooks?**  
A: Only if you make them public. By default, notebooks are private.

**Q: What if I run out of GPU time?**  
A: You'll need to wait until it resets (usually weekly) or upgrade to Kaggle's paid tiers.

**Q: Can I use this without Zrok?**  
A: No, both Kaggle and Zrok tokens are required. Zrok provides the secure tunnel that connects SAGE to your notebooks for image generation.

**Q: What happens if I change my Zrok token?**  
A: Just update it in the configuration and click "🔄 Sync Zrok Dataset" - it will update everywhere. You'll need to restart any running notebooks for them to use the new token.

**Q: Can I have multiple notebooks running at once?**  
A: Technically yes, but you typically only need one running at a time. Multiple notebooks consume more GPU quota. SAGE will use whichever tunnel is available.

**Q: How long does it take for a notebook to start?**  
A: Initial startup takes 2-10 minutes depending on the model size. The notebook needs to download the AI model, set up the environment, and create the Zrok tunnel.

**Q: Which model should I use?**  
A: 
- **Fast results**: SDXL DreamShaper with LCM (4-8 steps, very fast)
- **Anime/character art**: Counterfeit-V3
- **Precise control**: Counterfeit-V3 with ControlNet
- Try different models to find your favorite!

**Q: What does "img2img" mean?**  
A: Image-to-Image lets you upload an existing image and transform it based on your text description. Think of it as "editing" an image with AI.

**Q: What does "txt2img" mean?**  
A: Text-to-Image creates a completely new image from just your text description. This is the classic "AI art generation" feature.

**Q: What is ControlNet?**  
A: ControlNet lets you use a reference image to guide the AI. For example, you can use a pose skeleton to control character positions, or an edge map to control composition.

**Q: Can I add my own notebooks?**  
A: Yes! Just place your .ipynb file in the `notebooks/kaggle/` folder. Make sure it follows the same pattern as the existing notebooks (sets up Zrok tunnel at the end).

**Q: Do I need to keep this page open?**  
A: No! Once the notebook is running and the tunnel is established, you can close this page. The notebook will keep running on Kaggle until it times out or you stop it.

---

## Getting Help

If you encounter issues:

1. Check the messages at the top of the page (green = success, yellow = warning, red = error)
2. Review this guide's troubleshooting section
3. Make sure all credentials are entered correctly
4. Check that your Kaggle account is active and verified

---

## Next Steps

Now that you understand the system:

1. ✅ **Configure your API credentials** (Kaggle + Zrok)
2. ✅ **Choose a Stable Diffusion model** (see "Available Stable Diffusion Models")
3. ✅ **Start the notebook** (🔄 Sync & Run)
4. ✅ **Wait for Zrok tunnel** (2-10 minutes, check 🌐 Zrok section)
5. ✅ **Generate images in SAGE** (go to SAGE's main interface)
6. ✅ **Create beautiful AI art!** 🎨

### Your First Image Generation

**Complete beginner flow:**

1. Go to **🔑 API Configuration**
2. Enter your Kaggle username, API key, and Zrok token
3. Click **💾 Save All Credentials**
4. Scroll to **📚 Notebooks**
5. Find **"SDXL DreamShaper with LCM"** (fastest model)
6. Click **🔄 Sync & Run**
7. Wait for confirmation message
8. Scroll to **🌐 Zrok** section
9. Click **🔄 Reload** every 30 seconds
10. When you see an active tunnel → **You're ready!**
11. Go to SAGE's main interface
12. Describe the image you want
13. Click generate
14. Watch your image appear! 🎉

Happy creating! 🚀✨
