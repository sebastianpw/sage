# SAGE Quick Start Guide

## 1. Dashboard
- The **central point** to operate everything.
- Access all main features from here.

## 2. External Image Services
- SAGE can use services like **Pollinations** or **Freepik**, as well as virtual notebooks.
- Make sure to add your **(free) API keys** under:
  `Dashboard > 🔑 API Tokens`
- Check: `Dashboard > Tools > Notebooks`. Make sure to ALWAYS manually stop notebooks in your kaggle account "View active events" on kaggle.com (after a complete image generation session with multiple image generations) to only use GPU hours when you are really generating images

## 3. regenerate_images Flag
- Any entity like for example sketches or characters has a flag for regenerate_images [value: 0 OR 1]
- an image generation run for an entity will only generate images for rows with regenerate_images = 1
- either use the entity CRUD and look for ♾️ in each row/item or use the floatool ♻️ that opens a batch mode to flag multiple rows of an entity 

## 4. Genframe Settings
- SAGE scheduler depends on **correct genframe settings** to work with any image service.
- Check: `Dashboard > Scheduler > switch genframe ...`.

## 5. Notebook Tunnels
- SAGE uses **tunnels** for notebooks.
- Scheduler needs to know the **active notebook zrok tunnel**.
- Check: `Dashboard > Scheduler > update zrok tunnel`.

## 6. Styles
- SAGE uses one or more **style settings** for image generation.
- At least **one style must be activated** for image generation.
- Note: `visible` flag controls gallery visibility.
- Check: floatool > styles (🎨) **Access Floatool**: **  [☰]   ** OR **[    ☰ 🔮  🛢️  👤🎨♻️⚙️📓️]**

## 7. Composites
- Generated from **up to 3 source images**.
- Only works with **Freepik genframe option**.

## 8. Generatives
- Uses **img2img**.
- Ensure the selected notebook **supports img2img**.
- pollinations.ai API does support img2img but only with a (free) api key

## 9. ControlNet
- Still in development.
- **Not currently usable**.

## 10. Chat
- Use **multiple chat models**.
- Make sure to add your **(free) API keys** under:
  `Dashboard > 🔑 API Tokens`.
