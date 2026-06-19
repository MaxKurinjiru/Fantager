# Fantager - Screens Overview

*This document serves as an overview of all game screens for detailed specifications to individual roles (frontend, backend, UX/UI designer, etc.).*

## Summary
- Purpose: Provide a single, role-friendly reference of every in-game screen to support frontend, backend, and design implementation.
- Audience: Frontend engineers, backend engineers, UX/UI designers, product managers, and QA.
- Coverage: 20 core screens covering team management, combat, economy, marketplace, HQ, training, and community features.
- How to use: Use each screen section for UI specs; consult "Backend Requirements" for API needs and "Notes for Further Specification" for cross-cutting concerns.
- Status: Draft — ready for review and role-specific breakdowns.

---

## Site Structure

The site is divided into two main sections:

- **Public section** (`/`) — Unauthenticated. Includes Homepage, wiki/help, news archive. Login/Register are modal windows on these pages. See [00-public-pages.md](screens/00-public-pages.md).
- **Inside section** (authenticated) — Game content. Team Dashboard is the default landing page after login. All screens below belong to this section.

---

## Table of Contents

- [Summary](#summary)
- [Site Structure](#site-structure)
- [1. Kingdom Selection Screen](#1-kingdom-selection-screen)
- [2. Team Dashboard (Main Screen)](#2-team-dashboard-main-screen)
- [3. Hero Roster Screen](#3-hero-roster-screen)
- [4. Hero Detail Screen](#4-hero-detail-screen)
- [5. Training Screen](#5-training-screen)
- [6. Trainer Management Screen](#6-trainer-management-screen)
- [7. Formation Setup Screen](#7-formation-setup-screen)
- [8. Headquarters Screen](#8-headquarters-screen)
- [9. Summoning Chamber Screen](#9-summoning-chamber-screen)
- [10. Item/Equipment Screen](#10-itemequipment-screen)
- [11. Spell Management Screen](#11-spell-management-screen)
- [12. Combat/Battle Screen](#12-combatbattle-screen)
- [13. League Screen](#13-league-screen)
- [14. Calendar Screen](#14-calendar-screen)
- [15. Marketplace Screen](#15-marketplace-screen)
- [16. Graveyard Screen](#16-graveyard-screen)
- [17. Community Screen](#17-community-screen)
- [18. Arena Management Screen (Optional/Extended Feature)](#18-arena-management-screen-optionalextended-feature)
- [19. Player Profile & Settings Screen](#19-player-profile--settings-screen)
- [20. Crafting Screen (Deferred)](#20-crafting-screen-deferred)
- [Notes for Further Specification](#notes-for-further-specification)
- [UX/UI Design Requirements](#uxui-design-requirements)
- [Backend Requirements](#backend-requirements)
- [Database Schema Implications](#database-schema-implications)

---

## 1. Kingdom Selection Screen
**When:** During account creation (one-time only)

### Displayed Information:
- List of available Kingdoms (servers)
- For each Kingdom:
  - Name and theme/lore
  - Main language
  - Time zone
  - Game speed
  - Current player count / Max capacity
  - Season length
  - Kingdom icon/flag

### Possible Actions/Buttons:
- **Select Kingdom** - confirm selection (permanent, cannot be changed later)
- **Show Details** - expand information about specific Kingdom
- **Filter/Sort** - by language, game speed, occupancy

### Backend Requirements:
- API endpoint for fetching Kingdom list
- Kingdom availability validation (capacity)
- Writing selection to player profile

---

## 2. Team Dashboard (Main Screen)
**When:** Default screen after login

### Displayed Information:
- **Team Info Panel:**
  - Team name, emblem, colors
  - Team Reputation
  - Win/Loss record
  - Team Morale (value + indicator)
  - Team Chemistry (value + indicator)
- **Quick Stats:**
  - Number of heroes in roster
  - Current Gold, Essence
  - Next scheduled match (time, opponent)
  - Current league tier & position
- **Team Chronicle (recent events):**
  - Last 5 entries from `team_chronicle` (ownership, season, summons, …)
  - Link to full filtered history at `/app/chronicle`
  - See [team-chronicle-system.md](../systems/team-chronicle-system.md)
- **Shortcuts:**
  - Quick access to Formation, Training, Marketplace, League

### Possible Actions/Buttons:
- **View Full Roster** - navigate to Hero Roster Screen
- **Manage Headquarters** - navigate to HQ Screen
- **Check League** - navigate to League Screen
- **Go to Marketplace** - navigate to Marketplace
- **View Calendar** - navigate to Calendar
- **View Team Chronicle** - navigate to `/app/chronicle`
- **Team Settings** - change name, emblem, colors

### Backend Requirements:
- Dashboard aggregation endpoint (stats, notifications, recent chronicle via `TeamChroniclePresenter`)

---

## 3. Hero Roster Screen
**When:** Display all team heroes

### Displayed Information:
- **Hero List (table/cards):**
  - Hero name
  - Race (icon)
  - Level
  - Age (+ phase icon: Junior/Prime/Veteran/Elder)
  - Primary stats (STR, DEX, KON, SPD, INT, WIL, CHA, LCK) — values 1–20, compact display
  - Form (%)
  - Fatigue (%)
  - Morale (value + icon)
  - Equipped items (icons)
  - Status (available, tired, training, in match)
- **Filtering & Sorting:**
  - Filter by race, level, status
  - Sort by stat, age, form, morale

### Possible Actions/Buttons:
- **Select Hero** - navigate to Hero Detail Screen
- **Quick Actions Dropdown:**
  - Train Hero
  - Equip Items
  - Manage Spells
  - Sell on Marketplace
- **Multi-select Actions:**
  - Batch training
  - Formation assignment
- **Add New Hero** - navigate to Summoning Chamber or Marketplace

### Backend Requirements:
- Heroes list endpoint with filtering/sorting parameters
- Batch operations support

---

## 4. Hero Detail Screen
**When:** Detail view of a specific hero

### Displayed Information:
- **Hero Header:**
  - Name (editable)
  - Race + icon
  - 3D/2D hero avatar
  - Level + XP progress bar
  - Age (+ milestone indicator)
- **Primary Attributes:**
  - STR, DEX, KON, SPD, INT, WIL, CHA, LCK (values + race flat bonus tooltip)
- **Secondary Attributes:**
  - Form (% + visual indicator)
  - Fatigue (% + visual indicator)
  - Morale (value + mood icon)
  - Magic Capacity (current/max slots)
- **Equipment Slots (visual):**
  - Main Hand
  - Off-Hand
  - Head, Body, Hands, Feet
  - Amulet, Ring 1, Ring 2
  - (display empty slots as placeholders)
- **Equipped Spells:**
  - Spell icons + names (up to 5 slots)
  - School mastery levels (Fire, Water, Air, Earth, Light, Dark)
- **Statistics:**
  - Total battles
  - Wins/Losses
  - Combat deaths (count)
  - Training sessions completed
- **History Log:**
  - Recent actions (matches, trainings, events)

### Possible Actions/Buttons:
- **Train Attributes** - navigate to Training Screen for this hero
- **Manage Equipment** - navigate to Equipment Screen
- **Manage Spells** - navigate to Spell Management Screen
- **Assign to Formation** - quick add to formation
- **Sell on Marketplace** - list hero for sale
- **Convert to Trainer** (if Veteran+) - permanent conversion
- **View Full Stats** - expanded stat detail
- **Rename Hero** - inline editing

### Backend Requirements:
- Hero detail endpoint (full data)
- Hero update endpoint (rename, stats, equipment)
- Trainer conversion endpoint

---

## 5. Training Screen
**When:** Training hero attributes and skills

### Displayed Information:
- **Trainers Overview Panel:**
  - List of team's Trainers
  - For each Trainer:
    - Name, race, age
    - Configuration: Focus type (Attribute, Magic, Form, or Idle) and target attribute
    - Slots occupied vs. slots limit
    - List of assigned heroes currently training under this Trainer
- **Active Team Status:**
  - Whether training assignments and focus changes are currently **locked** (from Tuesday 12:00 to Thursday 10:00)
  - Next training tick execution time (weekly Thursday at 10:00)

### Possible Actions/Buttons:
- **Configure Trainer Focus** - change focus type (Attribute, Magic, Form, Idle) and target attribute
- **Assign Hero to Trainer** - assign available hero to a trainer's training slot
- **Unassign Hero from Trainer** - remove a hero from trainer's slot
- **View Trainer List** - navigate to Trainer Management

### Backend Requirements:
- GET `/api/v1/training/trainers` — List team's trainers, current configurations, hero slot occupancy, limits, and team lock status.
- POST `/api/v1/training/trainers/{id}/configure` — Configure trainer focus (request parameters: `type`, `attribute`).
- POST `/api/v1/training/trainers/{id}/assign` — Assign a hero to a trainer's slot (request parameter: `hero_id`).
- POST `/api/v1/training/trainers/{id}/unassign` — Unassign a hero from a trainer (request parameter: `hero_id`).

---

## 6. Trainer Management Screen
**When:** Managing trainers for attribute training

### Displayed Information:
- **Trainer List:**
  - Trainer name
  - Original race
  - Attribute values (STR–LCK, frozen at conversion, 1–20)
  - Age (+ Mortality Threshold warning when at or above threshold)
  - Status (Active, Aging risk)
- **Trainer Detail (when selected):**
  - Full stats and training caps
  - Age progression timeline
  - Cost (if on Marketplace)

### Possible Actions/Buttons:
- **Sell on Marketplace** - list trainer
- **Buy from Marketplace** - navigate to Marketplace filtered by Trainers
- **Convert Hero to Trainer** - permanent conversion (no specialty; assign on Training Screen)

Note: Trainers act as training leaders. Their training focus (Attribute, Magic, Form, or Idle) and hero assignments are configured on the Training Screen.

### Backend Requirements:
- Trainers list endpoint
- Marketplace integration
- Trainer conversion endpoint

---

## 7. Formation Setup Screen
**When:** Setting up formations for combat

### Displayed Information:
- **Formation Selector:**
  - Up to 4 saved formations (editable names)
  - Default formation indicator
- **Formation Layout (visual grid):**
  - **Front Line (3 positions)**
  - **Back Line (3 positions)**
  - Positions show drag&drop hero cards or empty slots
- **Hero Cards (in layout):**
  - Avatar/icon
  - Name
  - Level
  - Primary role/specialization (icon)
  - Quick stats
- **Hero Pool (sidebar):**
  - Available heroes for assignment
  - Filter by role, stats, race
- **Strategy Settings Panel:**
  - **Pre-match Approach:** Aggressive / Balanced / Defensive (radio buttons)
  - **Per-Hero Settings (when hero is selected in layout):**
    - **Targeting Priority:** Priority 1-6 or Flexible (dropdown for each priority)
    - **Action Sequence:** Attack / Cast Spell / Defend / Heal / Buff / Debuff / Auto-Suggest (action order, drag&drop)
    - **Spell Priority (Formation-level):**
      - Spell selection (dropdown from known spells)
      - Casting conditions (On Low Health, On Low Morale, etc.)
      - Spell targets (Self, Lowest Health, Highest Priority, Area)
    - **Conditional Tactics:** trigger and action settings
- **Formation Synergy Indicator:**
  - Race relationship warnings/bonuses
  - Role balance check (tank, damage, support, healer)
  - Team chemistry preview

### Possible Actions/Buttons:
- **Drag & Drop Heroes** - move heroes between Pool and Layout positions
- **Swap Heroes** - swap two heroes
- **Clear Slot** - remove hero from position
- **Save Formation** - save changes
- **Clone Formation** - duplicate to second slot
- **Set as Default** - mark formation as default
- **Test Formation** - simulation or practice match
- **View Opponent Formation** (before match) - if available
- **Quick Fill** - auto-suggest optimal formation

### Backend Requirements:
- Formation GET/POST/PUT endpoints
- Formation validation (6 heroes required, unique heroes)
- Synergy calculation API
- Formation simulation/testing

---

## 8. Headquarters Screen
**When:** Managing headquarters and facilities

> **Implementation note:** HQ is a unified hub at `/app/hq`. Arena and Summoning Chamber open as facility panels (`?facility=`). Seven facilities (Forge removed — crafting deferred).

### Displayed Information:
- **HQ Overview:**
  - Headquarters Level (total)
  - Visual theme preview (icon/illustration)
  - Arena Adaptation (currently selected race + summoning effects)
- **Facilities List (7):**
  - **Training Facilities:** Level, Bonus (efficiency %), Upgrade Cost (Gold), Next Level Bonus
  - **Medical Wing:** Level, Bonus (recovery %), Upgrade Cost
  - **Library/Academy:** Level, Bonus (magic training %), Upgrade Cost
  - **Treasury:** Level, Bonus (passive income), Storage Capacity, Upgrade Cost
  - **Barracks:** Level, Roster Capacity (starting: 10), Morale Recovery Bonus, Upgrade Cost
  - **Summoning Chamber:** Level, Summon Quality, Upgrade Cost, Summons Used, Max Summons per Cycle (based on Kingdom game speed)
  - **Arena:** Level, Seating Capacity, Ticket Revenue (per cycle), Home Advantage Bonus, Upgrade Cost
- **Passive Bonuses Summary:**
  - Total bonus from facilities (XP %, fatigue reduction, etc.)
  - Race-specific bonuses

### Possible Actions/Buttons:
- **Upgrade Facility** - confirm upgrade (modal with cost confirmation)
- **Change Arena Adaptation** - dropdown with races, confirm modal (weekly lock cycle)
- **Customize Theme** - navigate to visual customization (if implemented)
- **Visit Summoning Chamber** - navigate to Summoning Screen
- **HQ Settings** - access settings, visibility for community

### Backend Requirements:
- HQ data endpoint
- Facility upgrade endpoint (validation, cost deduction)
- Arena adaptation change endpoint
- Passive bonuses calculation

---

## 9. Summoning Chamber Screen
**When:** Recruiting new junior heroes

> **Implemented** as an HQ facility panel (`/app/hq?facility=summoning_chamber`). See [09-summoning-chamber.md](screens/09-summoning-chamber.md).

### Displayed Information:
- **Summon Status:**
  - Summons Used this Cycle
  - Max Summons per Cycle (based on Kingdom game speed)
- **Summon Parameters:**
  - Arena Adaptation (displaying the adapted race of the home arena)
  - Potential summonable races list (based on affinity and relations with the adapted race)
  - Starting level: **1**
  - Age range preview (Min Age - Max Junior Age for summonable races)
  - Expected stat range (based on race flat bonuses and Summoning Chamber level)
  - Summon Cost (Gold)
- **Recent Summons (history):**
  - Recently acquired heroes
  - Their basic stats

### Possible Actions/Buttons:
- **Summon Hero** - start summon process (animation, reveal)
- **View Summoned Hero** - after summon navigate to Hero Detail
- **Buy Another Slot** - expand summon capacity

### Backend Requirements:
- Summon availability check
- Random hero generation (race, age, base stats)
- Summon endpoint (POST)

---

## 10. Item/Equipment Screen
**When:** Managing inventory and hero equipment

### Displayed Information:
- **Inventory Panel:**
  - List of all owned items
  - Filter by type (Weapon, Armor, Accessory), rarity, equipped/unequipped
  - Each item displays:
    - Icon
    - Name
    - Rarity (color/badge)
    - Slot type
    - Attribute bonuses
    - Durability (if applicable)
    - Equipped by (hero name or "Unequipped")
- **Hero Equipment Panel (side-by-side or split):**
  - Selected hero (dropdown)
  - Visual equipment paperdoll/grid:
    - Main Hand, Off-Hand
    - Head, Body, Hands, Feet
    - Amulet, Ring 1, Ring 2
  - Stat summary with equipment vs. without

### Possible Actions/Buttons:
- **Equip Item** - assign item to hero (drag&drop or click-select)
- **Unequip Item** - remove item from slot
- **Swap Items** - exchange between two heroes
- **Sell Item** - list on Marketplace
- **Dismantle Item** - dismantle for Essence (confirm modal)
- **Craft Item** - navigate to Crafting Screen (if implemented)
- **Repair Item** - restore Durability (Essence cost)
- **Enchant Item** - navigate to Enchanting (if implemented)
- **Filter/Sort** - filtering controls
- **Compare Items** - multi-select to compare bonuses

### Backend Requirements:
- Inventory list endpoint
- Equipment GET/PUT endpoints
- Dismantle/repair/craft endpoints
- Validation (race restrictions, slot compatibility)

---

## 11. Spell Management Screen
**When:** Managing hero spells

### Displayed Information:
- **Hero Selector:**
  - Dropdown hero selection
  - Current Magic Capacity (X/5 slots)
  - School Mastery levels (Fire, Water, Air, Earth, Light, Dark) - visual progress bar or tiers
- **Known Spells Library:**
  - List of all learned spells
  - Filter by school, tier, type (offensive/defensive/utility)
  - Each spell:
    - Icon
    - Name
    - School + tier
    - Effect description
    - Mana cost (if applicable)
    - Cooldown (if applicable)
    - Equipped status (checkmark)
- **Equipped Spells Panel:**
  - Slot 1-5 (based on Magic Capacity)
  - Drag&drop or click-select from Known Spells
  - Display empty slots as "Learn New Spell"
- **Available Spells (Store/Academy):**
  - Spells available to learn (filtered by School Mastery requirements)
  - Cost (Gold + Essence)
  - Requirements (Mastery tier)

### Possible Actions/Buttons:
- **Equip Spell** - add spell to slot
- **Unequip Spell** - remove from slot (remains in Known Spells)
- **Learn New Spell** - purchase/learn spell (cost confirm modal)
- **Swap Spells** - change order of equipped spells
- **View Spell Details** - expand full description, damage calculations
- **Train School Mastery** - navigate to Training Screen (Magic Training tab)
- **Expand Spell Slots** - navigate to Training Screen for Magic Capacity training

### Backend Requirements:
- Hero spells endpoint (known spells, equipped spells)
- Spell library endpoint (available spells with requirements)
- Learn spell endpoint (POST with cost deduction)
- Equip/unequip endpoint

---

## 12. Combat/Battle Screen
**When:** During simulation or watching a match

### Displayed Information:
- **Battle Header:**
  - Opponent Team Name & Logo
  - Match Type (League, Friendly, Dungeon, Arena)
  - Kill score (0–6 per team; e.g. 3–2). Forfeit: 3–0; double understaffed: 0–0
- **Combat Area (visual representation):**
  - **Front Line vs Front Line** (hero positions)
  - **Back Line vs Back Line**
  - Hero avatars/models in positions
  - Current HP bars above each hero
  - Status effects icons (buffs/debuffs)
  - Morale indicator per hero
- **Turn Indicator:**
  - Current turn number
  - Active hero highlight (whose turn)
  - Speed order queue (next turns preview)
- **Combat Log (scrollable feed):**
  - Action-by-action text log
  - Damage numbers
  - Spell casts
  - Deaths/resurrections
  - Morale changes
- **Team Stats (sidebar):**
  - Team Morale (value)
  - Remaining Heroes (X/6)
  - Formation integrity indicator

### Possible Actions/Buttons:
- **Pause/Resume** (if live simulation)
- **Speed Control** (1x, 2x, 4x)
- **Skip to End** - skip to result
- **View Detailed Stats** - expand combat statistics mid-battle
- **Surrender** (in some modes) - instant defeat
- **Return to Dashboard** (after end) - return

### Backend Requirements:
- Combat simulation engine (PHP worker)
- Combat state streaming (polling/HTTP fetch)
- Battle result endpoint
- Post-battle updates (XP, form, fatigue, morale, age)

---

## 13. League Screen
**When:** Display league system and matches

### Displayed Information:
- **League Overview:**
  - Current Season (number/name + remaining time)
  - Player's Current Tier (Premier, Division 1, 2, 3...)
  - Player's Group within Tier
  - Current Rank in Group
  - Points (Win/Draw/Loss record)
  - Promotion/Relegation zone indicator
- **Group Standings Table:**
  - Rank
  - Team Name
  - Played matches
  - Wins / Draws / Losses
  - Points
  - Goal Difference (if applicable) or Win %
  - Form (last 5 matches: W/D/L sequence)
- **Fixtures (Match Schedule):**
  - Upcoming matches (date, time, opponent)
  - Past matches (results, score, link to replay)
- **Seasonal Rewards Preview:**
  - Rewards for current rank at season end (Gold, items)
  - Promotion rewards

### Possible Actions/Buttons:
- **View Match Details** - navigate to match detail or replay
- **Prepare for Match** - navigate to Formation Setup
- **View Opponent Profile** - opponent profile (heroes, stats)
- **Check Other Groups/Tiers** - browsing entire league
- **View Season History** - historical seasons
- **League Rules & Regulations** - help/info modal

### Backend Requirements:
- League data endpoint (standings, fixtures, player rank)
- Match scheduling system (server tick)
- Season management (start/end, promotion/relegation logic)
- Rewards distribution

---

## 14. Calendar Screen
**When:** Displaying the kingdom schedule (server ticks, league fixtures, team training)

### Displayed Information:
- **Weekly Calendar Feed:**
  - Scheduled server ticks (icons/labels):
    - Fatigue & Form Recovery
    - League Matches
    - Training Queue Processing
    - Arena Ticket Revenue Distribution
    - Hero Aging Update
    - Marketplace listing expiry
    - HQ maintenance and weekly resets
  - League fixtures (home/away)
  - Team training queue completions (when scoped to player's team)
- **Filters:**
  - Show/hide system-only ticks
  - Team-only entries

### Possible Actions/Buttons:
- **Navigate weeks** — previous / next week
- **Toggle filters** — system ticks, team-only view
- **Set Reminder** — notification for upcoming tick (planned)

### Backend Requirements:
- Calendar feed endpoint (`GET /api/v1/kingdom/{id}/calendar`)
- Server tick schedule and timezone normalization
- Notification system (future)

> Dynamic world events (participation panels, event history) are deferred — see [future/world-events-system.md](future/world-events-system.md).

---

## 15. Marketplace Screen
**When:** Buying and selling heroes, items, trainers

> **Implemented** as part of the Economy hub at `/app/economy` (legacy `/app/marketplace` redirects). See [15-marketplace.md](screens/15-marketplace.md).

### Displayed Information:
- **Marketplace Tabs:**
  - **Heroes**
  - **Items** (Weapons, Armor, Accessories)
  - **Trainers**
- **Listings Grid/List:**
  - Thumbnail/icon
  - Name
  - Level (for heroes/trainers)
  - Age (for heroes/trainers)
  - Stats/Attributes (compact)
  - Rarity (for items)
  - Price (Gold)
  - Seller Name
  - Time Remaining (if auction/limited listing)
- **Filtering & Sorting:**
  - Filter by race, level, age phase, price range, rarity
  - Sorting: Price (Low-High, High-Low), Level, Age, Recently Listed
- **Search Bar:**
  - Text search by name

### Possible Actions/Buttons:
- **Buy Now** - instant purchase (confirm modal with cost)
- **Bid** (if auction system) - placing bid
- **View Details** - expand full hero/item/trainer detail
- **Add to Watchlist** - track item
- **List Item/Hero for Sale** - modal to create listing:
  - Price input
  - Duration selection (3d, 5d, 7d)
  - Transaction fee preview
- **My Listings** - navigate to My Active Listings panel
- **Purchase History** - navigate to Transaction History

### Backend Requirements:
- Marketplace listings endpoint (s filtering/sorting)
- Purchase endpoint (validation, currency deduction, item transfer)
- Listing creation/cancellation endpoints
- Transaction fee calculation
- Marketplace auction processing (server tick)

---

## 16. Graveyard Screen
**When:** Displaying fallen heroes (memorial)

### Displayed Information:
- **Graveyard List:**
  - Hero name
  - Race
  - Final Level
  - Age at Death
  - Mortality Threshold (reached/exceeded)
  - Cause of Death (Combat, Mortality Roll, etc.)
  - Total Battles Fought
  - Wins
  - Team/Player (if applicable)
  - Date of Death
- **Statistics Summary:**
  - Total Fallen Heroes
  - Average Lifespan
  - Most Battles Fought (legend hero)
- **Memorial Wall (visualization):**
  - Gravestones/icons for each hero
  - Click for detail modal

### Possible Actions/Buttons:
- **View Hero Details** - expand full history and stats
- **Filter by Race** - display only specific race
- **Sort by** - battles, level
- **Search** - search by name
- **Share Memorial** (optional social feature) - share on profile

### Backend Requirements:
- Graveyard list endpoint
- Hero permanent death logging
- Memorial statistics

---

## 17. Community Screen
**When:** Community features and interactions

### Displayed Information:
- **Community Tabs:**
  - **Leaderboards**
  - **Player Profiles**
  - **Mail/Messages**
  - **News Feed**
  - **Forums**
- **Leaderboards Panel:**
  - Categories: League Tier, Arena Revenue, Total Victories, Hero Level, Team Reputation
  - Top 10/50/100 players
  - Rank, Team Name, Value
  - Player's own rank highlight
- **Player Profile View:**
  - Team Name, Logo
  - Manager Name
  - Current League Tier & Rank
  - Win/Loss Record
  - Team Reputation
  - Notable Heroes (top 3 by level/stats)
  - Recent Match History
- **Mail Panel:**
  - Inbox (received messages)
  - Sent Messages
  - New message indicator
  - Message list: Sender, Subject, Date, Read/Unread
  - Message content (when selected)
- **News Feed:**
  - Kingdom announcements
  - System updates
  - Event notifications
  - Patch notes
- **Forums:**
  - Categories (Strategy, Trading, General Discussion, Bug Reports)
  - Thread list: Title, Author, Replies, Last Post
  - Thread view: OP post + replies (chronological)

### Possible Actions/Buttons:
- **View Profile** - view own or other player's profile
- **Send Message** - compose modal
- **Reply to Message** - reply
- **Delete Message** - delete
- **Post New Thread** - create forum thread
- **Reply to Thread** - add reply
- **Refresh Feed** - reload news
- **Filter Leaderboards** - change category

### Backend Requirements:
- Leaderboards endpoint (rankings data)
- Player profile endpoint (public data)
- Mail system (CRUD operations)
- News feed endpoint
- Forum CRUD endpoints
- Email notifications integration

---

## 18. Arena Management Screen (Optional/Extended Feature)
**When:** Managing home arena for home matches

> **Implemented** as an HQ facility panel (`/app/hq?facility=arena`). Fixed ticket price; revenue on league match tick. See [18-arena-management.md](screens/18-arena-management.md).

### Displayed Information:
- **Arena Status:**
  - Arena Level
  - Seating Capacity
  - Current Ticket Price
  - Revenue per Match Cycle
  - Home Advantage Bonus (%)
  - Next Home Match (date, opponent)
- **Match History:**
  - Home matches (results, attendance, revenue)
- **Revenue Analytics:**
  - Total Revenue this season
  - Average Attendance
  - Revenue trend chart

### Possible Actions/Buttons:
- **Upgrade Arena** - increase capacity (cost)
- **Set Ticket Price** - dynamic pricing (affects attendance)
- **Schedule Friendly Match** - invite opponent
- **View Match Replay** - replay of home match

### Backend Requirements:
- Arena data endpoint
- Revenue calculation (based on capacity, reputation, ticket price)
- Match scheduling

---

## 19. Player Profile & Settings Screen
**When:** Player account settings (navbar → Account Settings modal)

### Displayed Information:
- **Language & Display (implemented):**
  - Interface Language (Czech / English) — saved to `User.locale`
- **Interface preferences (implemented):**
  - Backdrop modal closing toggle — saved to `auth_user_settings.close_modal_on_backdrop` (default off)
- **Account Info (partial):**
  - Email (shown in change-email form placeholder)
  - Username, Kingdom, Member Since, Supporter Tier — **planned**
- **Notification Settings (planned):**
  - Email notifications (on/off for various categories)
  - In-game notifications (on/off)
- **Privacy Settings (planned):**
  - Profile Visibility (Public/Friends/Private)
  - Headquarters Visitor Access
  - Allow Trade Requests

### Possible Actions/Buttons:
- **Change Language** — locale switcher links (implemented)
- **Toggle backdrop modal closing** — auto-save checkbox (implemented)
- **Edit Email** — change email (verification required, implemented)
- **Change Password** — password update (**planned**)
- **Update Notifications** — save changes (**planned**)
- **Support & Donations** — navigate to supporter contribution page (**planned**)
- **Logout** — logout (navbar, not in modal)
- **Delete Account** — account deletion with confirm panel (implemented)

### Backend Requirements:
- `UserSettings` entity (`auth_user_settings`) for UI preferences — **implemented**
- `POST /app/settings/preferences` — update preferences — **implemented**
- Email change with verification — **implemented**
- Account cancellation with verification — **implemented**
- Bulk settings GET/PUT API — **planned**
- Privacy / notification settings — **planned**

---

## 20. Crafting Screen (Deferred)

> Crafting backend and UI were removed from the codebase. Design preserved in [future/crafting-system.md](future/crafting-system.md) and [future/crafting-screen.md](future/crafting-screen.md).

---

## Notes for Further Specification

### Frontend Requirements:
- Responsive design (desktop + mobile/tablet)
- Regular status updates/polling for combat and notifications
- Drag & Drop support (formation setup, equipment, spells)
- Data visualization (stats charts, progress bars, morale indicators)
- Filtering & sorting for all list views
- Modal/dialog system for confirmations
- Tooltip system for stats/bonuses explanations
- Theme customization support (dark/light mode)

### Backend Requirements:
- RESTful API for all screen data
- Server tick system (cron jobs) for scheduled processing
- Transaction system (atomic operations for economy)
- Combat simulation engine
- Marketplace anti-exploit measures
- Queue processing (training, crafting)
- RNG system for summons, crafting success, combat

### UX/UI Design Requirements:
- Consistent design system (colors, typography, spacing)
- Race-specific visual themes/colors
- Icon set for all entities (heroes, items, spells, facilities)
- Loading states & skeleton screens
- Error states & validation messages
- Tutorial/onboarding flow for new players
- Accessibility (keyboard navigation, screen readers)

### Database Schema Implications:
- Players, Heroes, Teams, Formations
- Items, Spells, Trainers
- Marketplace Listings, Transactions
- Combat Battles, Battle Logs
- League Seasons, Groups, Fixtures
- Calendar (server ticks, fixtures, training)
- Mail/Messages
- Graveyard Records
- User Settings

---

**End of document - version 1.0**
