# SAGE (Storyboard AI Generation Engine)
*AI-Powered Creative Production Pipeline*
## 1. System Overview
- **Name:** SAGE (Storyboard AI Generation Engine)
- **Type:** AI-Powered Creative Production Pipeline
- **Purpose:** Semantic modeling and automated visual generation for multimedia storytelling and filmmaking.
- **Architecture Pattern:** Entity-Relationship-Driven Content Generation
- **Deployment:** Mobile-first with distributed cloud AI services
## 2. Core Philosophy
- **Semantic Modeling:** Map story elements as structured database entities with rich relationships.
- **Visual-First Feedback:** Every entity definition immediately generates visual frames for iterative refinement.
- **Traceability:** Complete lineage tracking from concept to generated asset.
- **Resource Optimization:** Leverage **free cloud GPU resources** through orchestrated service coordination. Commercial options are compatible as well.
## 3. Technology Stack
### Backend & Database
- **Backend Framework:** `PHP/Symfony`
- **Database Engine:** `MySQL/MariaDB`
- **Database Scale:** 100+ tables
- **Database Patterns:**
    - Entity-Relationship modeling
    - Junction tables for many-to-many relationships
    - Database views for optimized queries
- **Database Management:** Direct in-app access to Adminer and phpMyAdmin for administration, SQL execution, and backups.
### Deployment & Infrastructure
- **Primary Environment:** Android/Termux
- **Containerization:** Docker Compose
- **Tunneling Services:**
    - Zrok
    - Cloudflared
- **Process Management:** Heartbeat-monitored PHP-FPM CLI scheduler
### AI Services & Models
- **Integration Pattern:** Multi-provider with fallback mechanisms managed via a unified API token system.
- **Providers:**
    - Pollinations.ai
    - Groq
    - Freepik
    - Google Gemini
    - Google Colab
    - Kaggle Notebooks
    - Custom Jupyter deployments
- **Models:**
    - Stable Diffusion variants
    - LCM
    - Flux Schnell
    - ControlNet
    - IPAdapter
    - MV-Adapter
## 4. Data Architecture
### 4.1. Core Entities
Represents the semantic building blocks of a story.
- **Types of Entities:**
    - **characters:** Story protagonists and supporting cast with visual and personality descriptors.
    - **character_poses:** Specific positioning and body language for characters.
    - **animas:** Animated or living elements that affect story dynamics.
    - **locations:** Geographic and spatial contexts for scenes.
    - **backgrounds:** Environmental settings and atmospheric elements.
    - **artifacts:** Objects and items that characters interact with.
    - **vehicles:** Transportation and mechanical story elements.
    - **scene_parts:** Atomic scene components combining multiple entity types.
- **Common Attributes:**
    - Textual descriptors for AI prompt generation
    - Visual style preferences
    - Relationship mappings
    - Generation flags and parameters
    - `img2img` source references
### 4.2. Frame System
Manages all generated content and tracks its full lineage.
- **Components:**
    - **frames:** Central table storing all generated images with metadata.
    - **frame_counter:** A thread-safe sequential naming system for generated assets.
    - **frames_2_***: A series of junction tables linking generated frames back to their source entities.
    - **frames_chains:** Table defining sequential frame relationships for history and animation.
    - **map_runs:** A versioning system for frame generation runs associated with entities.
- **Generation Flow:**
    1. Entity prompts and/or `img2img` sources are defined.
    2. `regenerate_images` flags are set on entity rows to mark them for processing.
    3. Generation jobs are triggered for each flagged entity.
    4. The scheduler executes generation via external AI services.
    5. Generated frames are validated and stored with full lineage metadata.
    6. Junction tables are updated to maintain entity-to-frame relationships.
    7. Gallery interfaces provide entity-specific browsing of the new content.
### 4.3. Auxiliary Systems
- **Spawns:**
    - **Purpose:** Manages user-uploaded reference images for `img2img` workflows.
    - **Integration:** Stored and managed via dedicated uploaders and batch import tools.
- **Generatives:**
    - **Purpose:** `img2img` transformations that reference existing entities as their source.
    - **Capabilities:** Enables cross-entity visual adaptations and style transfers.
- **Composites:**
    - **Purpose:** Multi-source `img2img` creations that blend or combine multiple images (generated or uploaded) and/or entities.
    - **Status:** Fully integrated as a distinct creative asset type.
- **Sketches:**
    - **Purpose:** Used for experimental iterations and concept exploration.
    - **Workflow:** Can be converted to production entities or generatives.
- **Prompt Matrix Blueprints:**
    - **Purpose:** Structured, reusable prompt templates for systematic visual exploration and consistency.
    - **Status:** Fully integrated as a core creative asset.
- **ControlNet Maps:**
    - **Purpose:** Provides pose, depth, and edge guidance for controlled image generation.
    - **Status:** Fully integrated for assignment to entities, with a dedicated gallery, CRUD, and scheduler automation.
## 5. AI Generation Pipeline
### 5.1. Orchestration
- **Coordinator:** `genframe_db.sh` - The central generation bridge script.
- **Scheduler:** A PHP-based heartbeat system with automated lock management. Includes a dedicated UI for monitoring, manual task triggering, and log viewing.
- **Task Management:** AI-driven prioritization with dynamic scheduling.
### 5.2. Generation Modes
- **Text-to-Image:**
    - **Input:** Entity textual descriptors combined with style modifiers.
    - **Process:** Prompt engineering with configurable parameters.
    - **Output:** Base visual representations.
- **Image-to-Image:**
    - **Input:** A "Spawn" image plus transformation prompts.
    - **Process:** Reference-guided generation with adjustable strength controls.
    - **Output:** Adapted or refined visuals.
- **ControlNet-Guided:**
    - **Input:** Control maps (pose, depth, edge) plus prompts.
    - **Status:** Fully operational and automated.
    - **Potential:** Enables precise pose and composition control.
- **Multi-Image-to-Image:**
    - **Input:** Multiple reference images plus transformation prompts.
    - **Process:** Reference-guided generation using Nanobanana adapters.
    - **Output:** Coherent results that adapt or refine multiple input images.
### 5.3. Service Coordination
- **Multi-Provider Strategy:** Dynamic switching between Pollinations.ai, Freepik, Kaggle, tunneled cloud GPU notebooks, Google Gemini, and Groq.
- **Fallback Mechanism:** Graceful degradation when primary services are unavailable.
- **Tunnel Management:** Automatic cloud notebook access via Zrok.
- **Validation:** `FFmpeg`-based image integrity verification.
- **Retry Mechanisms:** Configurable retry logic with exponential backoff.
### 5.4. Quality Control
- **Validation:** Automated broken image detection and removal.
- **Lineage Tracking:** Complete parameter and source tracking for every generated frame.
- **Style Consistency:** A database-driven style system with entity-specific overrides.
- **Seed Management:** Ensures reproducible generation with optional seed specification.
## 6. User Interface Architecture
### 6.1. Entity Management
- **CRUD Interfaces:** Full create/read/update/delete for all entity types, unified under a consistent Gallery/CRUD/Scheduler split-button UI pattern.
- **Bulk Operations:** Batch importers for Spawns, Entity-to-Entity conversions, and Character Poses.
- **Relationship Management:** Visual tools for mapping relationships between entities.
- **Ordering System:** Drag-and-drop priority management with a dedicated 'Entity Order Reset' tool.
### 6.2. Gallery System
- **Entity-Specific Galleries:** Dedicated galleries for each entity type with contextual filtering options.
- **Presentation Views:** Multiple advanced viewing modes, including 'Wall of Images', 'Storyboards', 'Slideshow', and dynamic 'ScrollMagic' pages.
- **Video Management:** A dedicated 'Videos' playlist viewer for managing animated outputs.
- **Metadata Display:** Shows generation parameters, source entity, and lineage information for each asset.
### 6.3. Workflow Interfaces
- **Regeneration Flags:** A dedicated tool to mark entities for automated frame regeneration.
- **Scheduler Control:** A centralized dashboard for monitoring, with 'run now' buttons on each compatible entity for direct task execution.
- **Log Viewer:** An integrated scheduler log viewer for real-time diagnostics.
- **Chat System:** Context-aware AI assistance with multiple chat models, including a 'GPT conversations' history importer and viewer.
### 6.4. Global Utility Interface: The "Floatool"
A persistent, floating toolbox providing quick, context-aware navigation and access to core system functions from any view.
- **☰ (Menu):** Floating navigation menu - click to expand/collapse, drag to reposition.
- **🔮 (Dashboard):** Link to the main Dashboard.
- **🛢️ (Database):** Quick access to database management tools (Adminer/phpMyAdmin).
- **👤 (Profile):** Access user profile and settings.
- **🎨 (Styles):** Open the style management interface.
- **♻️ (Regenerate):** Flag the current entity or view for image regeneration.
- **⚗️ (Generators):** Access AI generators to create content from predefined configs and auto-fill text fields.
- **⚙️ (Scheduler):** Open the scheduler menu for immediate task execution.
- **📓️ (Logs):** View the scheduler log.
## 7. Advanced Capabilities
### 3D Integration
- **Model Generation:** TODO: World Mirror and TripoSG for automated 3D mesh and texture creation.
- **Pose Systems:** `Mannequin.js` for browser-based 3D pose creation.
- **Pose Map Generation:** generation of OpenPose maps for ControlNet
- **Multi-View Generation:** MV-Adapter for creating consistent multi-angle visuals.
- **3D Model Viewer:** An integrated `Babylon.js` 3D viewer.
- **Reference Library:** Sketchfab integration for 3D model references.
### Video Production
- **Animation Generation:** External APIs to create video clips from entities.
- **Content Organization:** Manages and presents generated videos in a dedicated playlist interface.
### Automation
- **AI To-Do Management:** Intelligent prioritization of development and creative tasks.
- **Bookmark Navigation:** UI for organizing and AI-driven import of website references/resources.
- **Batch Processing:** Support for large-scale entity and frame operations.
- **Monitoring:** Heartbeat systems, automated locks, and health checks.
- **Code Intelligence:** An automated AI-based parser for the codebase, defining classes and methods for PHP, JS, Shell, and Python.
### Content Management & Production Tools
- **Version Control:** Entity versioning capabilities.
- **CMS Integration:** WYSIWYG page builder ('HTML Pages') with frame embedding.
- **Showcase Tools:** Presentation interfaces like 'Wall of Frames', 'Infinite Scroll Gallery', 'Slideshow', and 'Video Playlist'.

### Creative Storyboard System
- **Multi-Storyboard Support:** Create and manage multiple storyboards for different scenes, sequences, or versions.
- **Universal Asset Assignment:** Any gallery image (`characters`, `locations`, `composites`, etc.) can be linked to any storyboard.
- **Drag-and-Drop Sequencing:** Sort and re-order frames directly via drag & drop.
- **Automated Renaming:** One click auto-renames all files to match the new sequence (`Scene01_Shot01.png` etc.).
- **Integrated Preview:** View the storyboard as infinite magic scroll to check flow and pacing.
- **Batch Export:** “Download as ZIP” bundles all renamed frames into one archive for sharing.

## 8. Scalability Patterns
- **Database:**
    - **View Optimization:** Pre-joined queries for common data retrieval operations.
    - **Junction Efficiency:** Optimized many-to-many relationship handling.
    - **Metadata Separation:** Core entities are kept separate from generated content for performance.
- **Generation:**
    - **Distributed Processing:** Utilization of multiple, diverse AI service providers.
    - **Queue Management:** Priority-based task scheduling to manage workloads.
    - **Resource Pooling:** Shared cloud GPU access across multiple projects.
- **Storage:**
    - **Hierarchical Organization:** Entity-type-based file and directory organization.
    - **Metadata Tracking:** Database-driven file management.
    - **Cleanup Automation:** Automated removal of failed or broken generations.
## 9. Workflow Patterns
### Primary Creative Workflow
1.  Define entities (characters, locations, etc.).
2.  Generate initial visual frames for each entity.
3.  Review the generated content in dedicated galleries.
4.  Refine entity definitions and regenerate as needed.
5.  Compose `scene_parts` by combining entities.
6.  Generate visualizations for the complete scenes.
### Iterative Refinement Workflow
- **`img2img` Chains:** Sequential refinement through multiple generation passes.
- **Style Exploration:** Systematic variation of styles across entities.
- **Cross-Entity Adaptation:** Maintaining visual consistency between different story elements.
### Production Pipeline Workflow
- **Storyboard Generation:** Automated scene visualization for pre-production.
- **Asset Export:** Flagging and extracting content for use in external tools.
- **Version Management:** Tracking iterations and maintaining distinct versions of entities and frames.
## 10. Integration Interfaces
- **AI Services:**
    - **API Abstraction:** A unified interface for multiple AI providers (Pollinations, Groq, Freepik, Gemini, etc.).
    - **Parameter Management:** Service-specific parameter mapping and optimization.
    - **Error Handling:** Robust failure recovery and alternative service routing.
- **External Tools:**
    - **Cloud Notebooks:** Dynamic tunnel management for on-demand GPU access.
    - **Image Hosting:** Temporary hosting for cross-service workflows.
    - **Model Repositories:** Integration with HuggingFace and Kaggle for public datasets and models.
- **Development Ecosystem:**
    - **Version Control:** Git
    - **Package Management:** Composer
    - **Containerization:** Docker
## 11. Technical Innovations
- **Mobile-First Deployment:** A full production AI pipeline running on Android via Termux.
- **Zero-Cost Infrastructure:** Enterprise-grade capabilities built using free-tier and personal cloud resources, with the option for paid APIs.
- **Semantic Generation:** An entity-relationship-driven approach to automated content creation.
- **Visual Database:** Using the database as a visual content management and generation system.
- **Distributed Orchestration:** Seamless coordination of multiple, independent cloud AI services.
- **Lineage Preservation:** Complete and auditable traceability from initial concept to final generated asset.
## 12. Use Cases
- **Independent Filmmaking:** A complete pre-production visualization pipeline.
- **Storyboard Automation:** Rapid scene and character visualization.
- **Concept Exploration:** Iterative visual development of story elements.
- **Asset Generation:** Automated creation of consistent visual assets for games, films, or marketing.
- **Production Planning:** Visual scene composition and relationship modeling.
## 13. Extensibility
- **New Entities:** The framework supports arbitrary new entity types with minimal schema changes.
- **AI Models:** A plugin-style architecture allows for easy integration of new AI services and generation models.
- **Workflow Automation:** The scheduler system supports the execution of arbitrary shell scripts, enabling custom automation.
- **Interface Customization:** Modular gallery and CRUD systems can be easily extended for new entity types.