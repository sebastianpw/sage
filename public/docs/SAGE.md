# SAGE Quick Start Guide

## 1. Dashboard
- The **central point** to operate everything.
- Access all main features from here.

## 2. External Image Services
- SAGE can use services like **Pollinations** or **Freepik**, as well as virtual notebooks.
- Make sure to add your **(free) API keys** under:
  `Dashboard > 🔑 API Tokens`
- Check: `Dashboard > Tools > Notebooks`. Make sure tonALWAYS manually stop notebooks in your kaggle account "View active events" to only use GPU hours when you are really generating images

## 3. Genframe Settings
- SAGE scheduler depends on **correct genframe settings** to work with any image service.
- Check: `Dashboard > Scheduler > switch genframe ...`.

## 4. Notebook Tunnels
- SAGE uses **tunnels** for notebooks.
- Scheduler needs to know the **active notebook zrok tunnel**.
- Check: `Dashboard > Scheduler > update zrok tunnel`.

## 5. Styles
- SAGE uses one or more **style settings** for image generation.
- At least **one style must be activated**.
- Note: `visible` flag controls gallery visibility.
- Check: `floatool > styles`.

## 6. Composites
- Generated from **up to 3 source images**.
- Only works with **Freepik genframe option**.

## 7. Generatives
- Uses **img2img**.
- Ensure the selected notebook **supports img2img**.

## 8. ControlNet
- Still in development.
- **Not currently usable**.

## 9. Chat
- Use **multiple chat models**.
- Make sure to add your **(free) API keys** under:
  `Dashboard > 🔑 API Tokens`.
