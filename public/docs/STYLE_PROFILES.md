# The Style Profile System: User Guide
Welcome to the Style Profile System! This powerful tool allows you to visually design, save, and manage complex artistic styles. You can then instantly convert these styles into detailed text prompts for use in AI image generation.
This guide will walk you through the three main screens of the system.
### Core Concepts
Before we dive in, let's understand three key ideas:
1.  **Design Axes:** These are the fundamental building blocks of a style. Think of them as individual sliders with descriptive words at each end (e.g., a slider ranging from "Glossy" to "Matte").
2.  **Style Profiles:** A "Style Profile" is a saved collection of all your slider settings. It's like a complete recipe for a unique artistic style.
3.  **AI Conversion:** This is the magic step where the system takes a saved Style Profile and translates all its slider values into a sophisticated, ready-to-use text prompt for an AI.
---
### Part 1: The Style Sliders (The Creator Workspace)
This is the heart of the system—where you'll do your creative work. You can get here by clicking **"Create new"** from the Admin screen or by opening an existing profile.
#### Filtering the Sliders (Choosing Your Canvas)
At the top of the screen, you'll see a filter bar with two dropdowns:
*   **Entity:** Think of this as the main subject of your style. Are you designing a style for a *Character*, a *City*, or something else? Selecting an entity here will change the available sliders to ones that are relevant to that subject.
*   **Category:** Within an entity, you can have categories. For example, a "Character" entity might have categories for "Appearance," "Clothing," and "Personality." Selecting a category further refines the list of sliders to a specific area of focus.
This filtering system ensures you always have the right set of tools in front of you for the style you want to create.
#### Designing a Style
The main part of the screen is a list of sliders, called **"Axes."** Each row represents one aspect of your style:
1.  **The Labels:** On the far left and right of the slider, you'll see descriptive words (e.g., "Painterly" vs. "Photoreal"). These are the "poles" of the axis.
2.  **The Slider:** Drag the slider left or right to lean your style toward one of the poles.
3.  **The Number Box:** You can also type a precise number from **0** (far left) to **100** (far right) into the box. The slider and number box are always in sync. A value of **50** is neutral.
Move down the list, adjusting each slider to dial in the exact look and feel you want.
#### Saving Your Work
Once you're happy with your slider settings, you need to save them as a **Style Profile**. At the top of the page:
1.  Give your style a **Profile Name** (e.g., "Cyberpunk Hero Style").
2.  Add a **Description** to help you remember the details.
3.  Click the blue **Save (DB)** button. This saves the profile to your central library so you can manage it later.
4.  Alternatively, click **Download JSON** to save a text file of your style profile directly to your computer. This is useful for backups or sharing with others.
---
### Part 2: The Style Profiles Admin (Your Library)
This screen is your library of all the styles you have saved. It provides a high-level overview and powerful tools for managing and using your profiles.
#### The AI Configuration
At the top of this page is a special "AI Generator Configuration" section. This allows advanced users to select different AI "brains" for translating your sliders into text. **For most users, leaving these dropdowns on their default settings is recommended.**
#### The Profile List
The main table lists all your saved profiles. For each one, you have a set of actions:
*   **Preview:** This is a quick way to inspect a profile. It opens a pop-up showing you the raw data of your slider settings in a text format (called JSON). From this pop-up, you can also test the AI conversion.
*   **Download:** Instantly downloads the profile's JSON file to your computer.
*   **Open:** This is the most common action. Clicking this will take you **back to the Style Sliders screen** with this specific profile loaded, ready for you to view or make further edits.
*   **Convert:** This is a powerful shortcut. It opens the same "Preview" pop-up but **immediately tells the AI to convert the style into a text prompt.** The final prompt will appear at the top of the pop-up, ready for you to copy. If you've converted a profile before, it will show you the previously generated result instantly.
*   **Delete:** Permanently removes the style profile from your library. This cannot be undone.
---
### Part 3: The Design Axes Admin (The Tool Builder)
This is an advanced screen for users who want to customize the system itself by creating or editing the sliders.
#### What is a Design Axis?
A "Design Axis" is the technical term for a single slider row. It has a name, two descriptive poles, and belongs to a specific group (Entity).
#### Managing Axes
The table on this screen lists every single slider available in the system.
*   **Filter and Search:** You can use the filter dropdowns and search bar at the top to quickly find a specific axis you want to edit.
*   **+ Create Axis:** Click this to open a pop-up where you can build a brand new slider from scratch.
*   **Edit:** Click this on any row to open the same pop-up and change an existing slider's details.
*   **Delete:** Permanently removes a slider from the system. Be careful, as this will also remove it from any Style Profiles that were using it.
#### Creating or Editing an Axis
When you open the pop-up, you'll see the following fields:
*   **Axis Name:** The main title for the slider (e.g., "Lighting Style").
*   **Axis Group:** The "Entity" this slider belongs to. This determines on which screen the slider will appear (e.g., `characters`).
*   **Left Pole:** The descriptive text for the `0` end of the slider (e.g., `Natural Light`).
*   **Right Pole:** The descriptive text for the `100` end of the slider (e.g., `Studio Light`).
*   **Notes:** A small, helpful description that appears below the axis name on the sliders screen.
By creating and managing these axes, you have full control over the creative tools available for designing your styles.


