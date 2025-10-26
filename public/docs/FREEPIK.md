# FREEPIK.md

# Connect Freepik with SAGE

This short guide explains how to create a Freepik account and connect it with your SAGE Dashboard.

** Please note that Freepik can do txt2img and img2img and is recommended to be used only for Composites as it is currently the only available free option in SAGE for Composites with more than one source image. It has a usage limit of about up to 100 images per day. Composites can have up to 3 source images in the free freepik version. Image generation uses nanobanana. **

---

## Step 1 — Create your Freepik account
1. Go to **[https://www.freepik.com/sign-up](https://www.freepik.com/sign-up)**  
2. Sign up with your **email**, **Google**, or **Apple** account.  
3. Confirm your email if Freepik asks you to.

That’s it — you now have a Freepik account.

---

## Step 2 — Get your Freepik API Token

1. After signing in, open **[https://www.freepik.com/api](https://www.freepik.com/api)**  
2. Click the button **“Get your API key”** or **“Generate API token.”**  
3. Copy the key that appears — it’s your personal Freepik token.  
4. Keep it private and do **not** share it with others.

---

## Step 3 — Save it in SAGE

1. Open your **SAGE Dashboard**  
2. Go to **🔑 API Tokens**  
3. Paste your **Freepik API Token** there and click **Save**

Once your token is saved, SAGE may use Freepik for multi-image composites (up to 3 source images). Please go to Dashboard > Scheduler, enter freepik in the red field and push ▶ button for: sw 🌠 Switch to API: pollinations, freepik, jupyter, jupyter_lcm 
