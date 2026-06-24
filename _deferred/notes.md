
### Mistrovství s postavou / stylem (Hero Mastery)
* **Konkrétní aplikace:**
  * **Mistrovství se zbraní a školou magie (Weapon & Magic Mastery):** Hrdina by mohl získávat zkušenosti s konkrétním typem vybavení (např. jednoruční meč + štít, obouruční sekera, magický urychlovač) nebo s konkrétní magickou školou (např. Fire, Water).
  * Pokud by hráč často měnil vybavení hrdiny, hrdina by přišel o bonusy za mistrovství, dokud se s novou zbraní nesžije. To by nutilo manažery budovat hrdiny s jasným herním stylem.

---

### Dynamické změny vyvážení (Kingdom Events)
* **Konkrétní aplikace:**
  * **Sezónní požehnání a kletby Království :** Vzhledem k tomu, že Fantager využívá systém oddělených serverů (Kingdoms), každý server by mohl mít týdenní nebo sezónní globální modifikátory. Příklad: „*Tento týden je aktivní Zatmění slunce: kouzla školy Dark Magic mají o 15 % vyšší účinnost, kouzla Light Magic jsou o 10 % slabší.*“
  * To by rozhýbalo trh na tržišti (Marketplace), jelikož by manažeři museli nakupovat a prodávat hrdiny podle aktuální situace.

---

### Unikátní rysy a povahy hrdinů (Traits & Personalities)
* **Konkrétní aplikace:**
  * **Osobnostní rysy hrdinů:** Při rekrutování hrdiny by mu byl náhodně přidělen rys ovlivňující hratelnost.
    * *Rychlý student:* +20 % k rychlosti tréninku atributů.
    * *Miláček publika:* Zvyšuje pasivní příjem ze vstupného v domácí aréně o 5 %, pokud je nasazen v zápase.
    * *Labilní:* Ztrácí morálku dvakrát rychleji, pokud v zápase zemře spojenec.
  * Tyto rysy by dodaly hrdinům individualitu a zvýšily jejich hodnotu na tržišti.

---

### Sponzoři a týmové úkoly (Sponsorships & Quests)
* **Konkrétní aplikace:**
  * **Sponzorské smlouvy v HQ:** V rámci správy základny by hráč mohl uzavírat partnerství. Sponzor by nabídl jednorázový finanční bonus nebo pravidelný příjem zlata, ale výměnou by požadoval specifické výsledky (např. „*Nasazuj v každém ligovém zápase alespoň 2 hrdiny rasy Elf*“ nebo „*Udržuj průměrnou týmovou morálku nad 80 %*“). Nesplnění by vedlo k penále nebo ztrátě reputace arény.

---

### Nové statistiky hrdinů (Hero Stats)

Aktuální atributy Fantageru (STR, DEX, KON, SPD, INT, WIL, CHA, LCK) pokrývají fyzické a magické základy. E-sportovní manažery často pracují se skrytými nebo psychologickými vlastnostmi hrdinů. Do hry by šlo přidat následující nízkoúrovňové statistiky:

#### 1. Chladnokrevnost (Clutch Factor / Composure) — Primární nebo sekundární stat
* **Koncept:** Určuje, jak se hrdina chová v krizových situacích (např. když je sám proti přesile nebo když mu zbývá méně než 30 % zdraví).
* **Vliv na hratelnost:**
  * Hrdina s vysokou chladnokrevností získá při nízkém zdraví nebo po smrti spojence dočasný bonus k obraně a přesnosti.
  * Hrdina s nízkou chladnokrevností naopak při oslabení týmu zpanikaří, což sníží jeho přesnost (DEX) a zvýší šanci, že jeho kouzlo selže.
  * *Třída v kódu:* Atribut by se přímo zapojoval do výpočtu modifikátorů v simulátoru souboje (`Combat Simulator`) při vyhodnocování úmrtí spojenců.

#### 2. Kooperace (Synergy Coefficient / Teamwork) — Sekundární stat
* **Koncept:** Určuje, jak efektivně dokáže hrdina spolupracovat s ostatními rasami v týmu.
* **Vliv na hratelnost:**
  * Násobí hodnoty z tabulky vztahů mezi rasami (Race Relationship Matrix).
  * Hrdina s vysokou kooperací (např. 1.5x) maximalizuje pozitivní morální bonusy, pokud vedle něj stojí spřátelená rasa (např. Elf s Člověkem).
  * „Vlk samotář“ (kooperace 0.2x) ignoruje morální postihy za přítomnost nepřátelských ras (např. trpaslík vedle orka), ale zároveň nezískává žádné synergie ze spojenectví.

#### 3. Konzistentnost (Consistency / Stability) — Sekundární stat
* **Koncept:** Určuje rozptyl výkonu hrdiny v jednotlivých kolech souboje.
* **Vliv na hratelnost:**
  * Většina útoků a kouzel má v RPG hrách rozsah poškození (např. 10–50).
  * Hrdina s vysokou konzistentností bude stabilně udělovat poškození kolem středu (např. 28–32).
  * Hrdina s nízkou konzistentností má obrovský rozptyl (může udeřit za 5, ale také kriticky za 90). Tento stat je úzce provázaný se štěstím (LCK).

---

### Nové budovy do základny (HQ Facilities)

Stávající budovy pokrývají trénink, léčení, magii, finance, ubytování, summoning a zápasy. Chybí zde však budovy pro aktivní skauting, správu předmětů a budování komunity/značky.

#### 1. Skautská kancelář / Cechovní agentura (Scouting Office)
* **Koncept:** Budova inspirovaná hledáním talentů v TFM2. Umožňuje aktivně vyhledávat hrdiny s konkrétními parametry namísto pouhého čekání na náhodný týdenní cyklus v Summoning Chamber.
* **Úrovně vylepšení:**
  * *Level 1:* Umožňuje vyslat 1 skauta, který po určitém čase (např. 1 serverový tick) přivede 3 kandidáty konkrétní rasy.
  * *Level 2–3:* Zvyšuje počet skautů a umožňuje filtrovat kandidáty podle preferovaného atributu (např. hledat hrdinu s vysokou silou STR).
  * *Level 4+:* Umožňuje skautovat v jiných Královstvích (serverech) a hledat hrdiny s unikátními rysy (Traits).

#### 2. Krčma u Arény / Společenský salón (Tavern / Recreation Lounge)
* **Koncept:** Místo dedikované výhradně pro budování vztahů a regeneraci morálky týmu, což je klíčové při kombinování nekompatibilních ras (např. Elfů a Orků).
* **Úrovně vylepšení:**
  * *Level 1:* Umožňuje umístit dva hrdiny různých ras ke společnému stolu. Během jednoho týdenního ticku se jejich vzájemný vztahový postih mírně sníží.
  * *Level 2+:* Odemkne pořádání týmových oslav (stojí zlato), které okamžitě obnoví morálku celému týmu po těžké porážce v lize.
  * *Level 3+:* Umožňuje podávat speciální nápoje, které dočasně zvýší Formu vybraných hrdinů pro nadcházející zápas za cenu vyšší únavy po něm.

#### 3. Kovárna a dílna (Forge & Workshop)
* **Koncept:** Budova zaměřená na zefektivnění práce s předměty (Item System) a spotřebou esencí (Essence).
* **Úrovně vylepšení:**
  * *Level 1:* Zpřístupňuje možnost opravy opotřebovaných předmětů (restaurování durability) s nižšími náklady na zlato.
  * *Level 2:* Zvyšuje výtěžnost esencí při rozebírání (dismantling) předmětů.
  * *Level 3+:* Umožňuje „překovat“ předmět – změnit jeden nechtěný atribut na předmětu za poplatek v esencích.

---

### Legacy systém hřbitova: Požehnání předků (Ancestral Blessing)
* **Konkrétní aplikace:**
  * **Odkaz padlých (Legacy Bonus):** Pokud hrdina zemře a je pohřben v rodinné hrobce, zanechá po sobě trvalý odkaz na základě svých životních úspěchů (počet odehraných zápasů, vítězství, získané tituly).
  * Příklad: Pohřbení trpasličího válečníka na vysoké úrovni s mnoha vítězstvími může přinést permanentní pasivní bonus `+0.5% k síle (STR)` pro všechny budoucí trpaslíky rekrutované v tomto HQ.
  * Hráči by tak nebyli z trvalé smrti (permadeath) svých veteránů pouze frustrovaní, ale brali by ji jako strategickou oběť pro posílení budoucích generací.

---

### Mentorský systém v tréninkové hale (Mentorship)
* **Konkrétní aplikace:**
  * **Přiřazení mentora:** V tréninkovém centru by hráč mohl spárovat jednoho veterána (nadlimitní věk) s jedním juniorem (podlimitní věk).
  * Během týdenních serverových ticků by junior získával bonus k rychlosti tréninku a navíc by existovala šance na přenos specifických znalostí – například by se od mentora mohl naučit základy magické školy (School Mastery), kterou mentor ovládá, aniž by musel platit plnou cenu v knihovně.

---

### Vlivy prostředí domácí arény (Arena Hazards / Turf Conditions)
* **Konkrétní aplikace:**
  * Manažer by mohl investovat do úpravy povrchu domácí arény, aby vyhovovala jeho sestavě a penalizovala soupeře.
  * **Příklady povrchů:**
    * *Blátivý terén (Muddy Ground):* Snižuje rychlost (SPD) všem hrdinům o 15 %, kromě Entů a Obříků (Giants), kteří jsou imunní.
    * *Magické prameny (Ley Lines):* Zvyšuje účinnost kouzel o 10 %, ale snižuje fyzickou obranu (armor) o 10 %. Ideální pro týmy plné mágů.
    * *Trpasličí kamenná podlaha:* Zvyšuje stabilitu a snižuje šanci na kritický zásah pro obě strany. Výhodné pro defenzivní týmy.
  * Soupeř (hostující tým) by před zápasem musel analyzovat povrch arény a případně upravit svou formaci nebo vybavení (např. obout lehčí boty).

---

### Dynamická přestupová okna a volní hráči (Transfer Windows & Free Agents)
* **Konkrétní aplikace:**
  * **Systém draftu volných hráčů (Free Agent Draft):** Jednou za sezónu by server vygeneroval několik vysoce kvalitních hrdinů s unikátními kombinacemi statistik bez vlastníka.
  * Manažeři by následně podávali tajné nabídky (blind bidding) v určitém časovém okně. Hrdinu by získal ten, kdo nabídne nejvíce zlata.
  * **Sezónní slevy na daních:** Během „přestupového okna“ by se královská daň z prodeje na tržišti snížila z výchozích 10 % na 5 %, což by stimulovalo aktivitu hráčů.
