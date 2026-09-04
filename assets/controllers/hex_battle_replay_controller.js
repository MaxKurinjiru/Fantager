import { Controller } from '@hotwired/stimulus';

/**
 * Slot-to-Hex axial coordinate mapping for 11×8 Battlefield (q = 0..10, r = 0..7).
 * Must match HexGridService::getInitialHexForSlot().
 * Team A (Home / Left): front (2,2/4/6), back (1,1/3/5).
 * Team B (Away / Right): front (8,2/4/6), back (9,1/3/5).
 */
const SLOT_HEX_MAP = {
    a: {
        back_1: { q: 1, r: 1 },
        front_1: { q: 2, r: 2 },
        back_2: { q: 1, r: 3 },
        front_2: { q: 2, r: 4 },
        back_3: { q: 1, r: 5 },
        front_3: { q: 2, r: 6 }
    },
    b: {
        back_1: { q: 9, r: 1 },
        front_1: { q: 8, r: 2 },
        back_2: { q: 9, r: 3 },
        front_2: { q: 8, r: 4 },
        back_3: { q: 9, r: 5 },
        front_3: { q: 8, r: 6 }
    }
};

/**
 * Stimulus controller for rendering and animating 11×8 Hexagonal Grid combat replays on HTML5 Canvas.
 */
export default class extends Controller {
    static targets = [
        'canvas',
        'playBtn',
        'roundLabel',
        'roundScrubber',
        'speedSelect',
        'combatTicker'
    ];

    static values = {
        runState: Object,
        combatLog: Object,
        labelPlay: { type: String, default: 'Play' },
        labelPause: { type: String, default: 'Pause' },
        labelRound: { type: String, default: 'Round __CURRENT__ / __MAX__' }
    };

    connect() {
        this.currentRound = 1;
        this.isPlaying = false;
        this.playbackSpeed = 1000; // ms per round
        this.animationTimer = null;

        this.gridWidth = 11;
        this.gridHeight = 8;
        this.hexRadius = 26;

        this.initCanvas();
        this.extractRoundEvents();
        this.render();
    }

    disconnect() {
        this.stopPlayback();
    }

    initCanvas() {
        this.canvas = this.canvasTarget;
        this.ctx = this.canvas.getContext('2d');

        const dpr = window.devicePixelRatio || 1;
        this.canvas.width = 760 * dpr;
        this.canvas.height = 520 * dpr;
        this.ctx.scale(dpr, dpr);
    }

    extractRoundEvents() {
        this.maxRounds = 1;
        if (this.hasRunStateValue && this.runStateValue.currentRound) {
            this.maxRounds = this.runStateValue.currentRound;
        } else if (this.hasCombatLogValue && this.combatLogValue.events) {
            const roundEvents = this.combatLogValue.events.filter(e => e.type === 'round_start');
            this.maxRounds = Math.max(1, roundEvents.length);
        }

        if (this.hasRoundScrubberTarget) {
            this.roundScrubberTarget.max = this.maxRounds;
            this.roundScrubberTarget.value = 1;
        }
        this.updateScrubberLabel();
    }

    togglePlay() {
        if (this.isPlaying) {
            this.stopPlayback();
        } else {
            this.startPlayback();
        }
    }

    startPlayback() {
        this.isPlaying = true;
        if (this.hasPlayBtnTarget) {
            this.playBtnTarget.textContent = this.labelPauseValue;
            this.playBtnTarget.setAttribute('aria-label', this.labelPauseValue);
        }

        this.animationTimer = setInterval(() => {
            if (this.currentRound >= this.maxRounds) {
                this.stopPlayback();
                return;
            }
            this.currentRound++;
            this.updateScrubber();
            this.render();
        }, this.playbackSpeed);
    }

    stopPlayback() {
        this.isPlaying = false;
        if (this.animationTimer) {
            clearInterval(this.animationTimer);
            this.animationTimer = null;
        }
        if (this.hasPlayBtnTarget) {
            this.playBtnTarget.textContent = this.labelPlayValue;
            this.playBtnTarget.setAttribute('aria-label', this.labelPlayValue);
        }
    }

    stepNext() {
        this.stopPlayback();
        if (this.currentRound < this.maxRounds) {
            this.currentRound++;
            this.updateScrubber();
            this.render();
        }
    }

    stepPrev() {
        this.stopPlayback();
        if (this.currentRound > 1) {
            this.currentRound--;
            this.updateScrubber();
            this.render();
        }
    }

    onScrub(e) {
        this.stopPlayback();
        this.currentRound = parseInt(e.target.value, 10);
        this.updateScrubberLabel();
        this.render();
    }

    onSpeedChange(e) {
        const factor = parseFloat(e.target.value);
        this.playbackSpeed = 1000 / factor;
        if (this.isPlaying) {
            this.stopPlayback();
            this.startPlayback();
        }
    }

    updateScrubber() {
        if (this.hasRoundScrubberTarget) {
            this.roundScrubberTarget.value = this.currentRound;
        }
        this.updateScrubberLabel();
    }

    updateScrubberLabel() {
        if (this.hasRoundLabelTarget) {
            this.roundLabelTarget.textContent = this.labelRoundValue
                .replace('__CURRENT__', String(this.currentRound))
                .replace('__MAX__', String(this.maxRounds));
        }
    }

    /**
     * Reconstruct combatant states (HP, KO, status) up to currentRound by folding log events.
     */
    getCombatantStatesForRound(sideKey) {
        if (!this.hasRunStateValue) return [];

        const sideData = sideKey === 'a' ? this.runStateValue.sideA : this.runStateValue.sideB;
        if (!sideData || !sideData.combatants) return [];

        const combatants = sideData.combatants.map(hero => {
            const maxHp = hero.derived ? hero.derived.maxHp : (hero.maxHp || 100);
            const initialCoords = (SLOT_HEX_MAP[sideKey] && SLOT_HEX_MAP[sideKey][hero.slot])
                ? SLOT_HEX_MAP[sideKey][hero.slot]
                : { q: hero.q || 0, r: hero.r || 0 };

            return {
                ...hero,
                currentHp: maxHp,
                maxHp: maxHp,
                isKo: false,
                q: initialCoords.q,
                r: initialCoords.r
            };
        });

        if (this.hasCombatLogValue && Array.isArray(this.combatLogValue.events)) {
            const events = this.combatLogValue.events;
            for (const event of events) {
                if (event.round !== undefined && event.round > this.currentRound) {
                    break;
                }

                if (event.type === 'move' && event.side === sideKey) {
                    const hero = combatants.find(c => c.slot === event.slot || c.heroId === event.hero_id);
                    if (hero) {
                        hero.q = event.to_q !== undefined ? event.to_q : event.q;
                        hero.r = event.to_r !== undefined ? event.to_r : event.r;
                    }
                } else if ((event.type === 'damage' || event.type === 'heal_applied') && event.target_side === sideKey) {
                    const hero = combatants.find(c => c.slot === event.target_slot || c.heroId === event.hero_id);
                    if (hero && event.hp_after !== undefined) {
                        hero.currentHp = Math.max(0, event.hp_after);
                        if (hero.currentHp <= 0) {
                            hero.isKo = true;
                        }
                    }
                } else if ((event.type === 'ko' || event.type === 'hero_defeated') && (event.side === sideKey || event.target_side === sideKey)) {
                    const hero = combatants.find(c => c.slot === event.slot || c.heroId === event.hero_id);
                    if (hero) {
                        hero.currentHp = 0;
                        hero.isKo = true;
                    }
                }
            }
        }

        return combatants;
    }

    render() {
        const ctx = this.ctx;
        ctx.clearRect(0, 0, 760, 520);

        ctx.fillStyle = '#0f172a';
        ctx.fillRect(0, 0, 760, 520);

        for (let q = 0; q < this.gridWidth; q++) {
            for (let r = 0; r < this.gridHeight; r++) {
                let fillColor = '#1e293b';
                let strokeColor = '#334155';

                if (q < 3) {
                    fillColor = '#1e1b2e';
                    strokeColor = '#451a2b';
                } else if (q > 7) {
                    fillColor = '#172554';
                    strokeColor = '#1e3a8a';
                }

                this.drawHex(q, r, fillColor, strokeColor);
            }
        }

        const combatantsA = this.getCombatantStatesForRound('a');
        const combatantsB = this.getCombatantStatesForRound('b');

        this.renderCombatantSide(combatantsA, '#ef4444', 'A');
        this.renderCombatantSide(combatantsB, '#3b82f6', 'B');
    }

    hexToPixel(q, r) {
        const size = this.hexRadius;
        const SQRT3 = Math.sqrt(3);

        const xSpacing = 1.5 * size;
        const ySpacing = SQRT3 * size;

        const totalGridWidth = (this.gridWidth - 1) * xSpacing + (2 * size);
        const totalGridHeight = (this.gridHeight - 1) * ySpacing + (ySpacing) + (ySpacing / 2);

        const offsetX = (760 - totalGridWidth) / 2 + size;
        const offsetY = (520 - totalGridHeight) / 2 + (ySpacing / 2);

        const x = offsetX + q * xSpacing;
        const y = offsetY + r * ySpacing + (q % 2 === 1 ? (ySpacing / 2) : 0);
        return { x, y };
    }

    drawHex(q, r, fillColor, strokeColor) {
        const { x, y } = this.hexToPixel(q, r);
        const size = this.hexRadius;

        this.ctx.beginPath();
        for (let i = 0; i < 6; i++) {
            const angle = (Math.PI / 180) * (60 * i);
            const hx = x + size * Math.cos(angle);
            const hy = y + size * Math.sin(angle);
            if (i === 0) {
                this.ctx.moveTo(hx, hy);
            } else {
                this.ctx.lineTo(hx, hy);
            }
        }
        this.ctx.closePath();
        this.ctx.fillStyle = fillColor;
        this.ctx.fill();
        this.ctx.strokeStyle = strokeColor;
        this.ctx.lineWidth = 1;
        this.ctx.stroke();

        this.ctx.fillStyle = '#475569';
        this.ctx.font = '8px monospace';
        this.ctx.textAlign = 'center';
        this.ctx.textBaseline = 'middle';
        this.ctx.fillText(`${q},${r}`, x, y + size * 0.65);
    }

    renderCombatantSide(combatants, color, sideCode) {
        if (!combatants || !combatants.length) return;

        combatants.forEach(hero => {
            const { x, y } = this.hexToPixel(hero.q, hero.r);
            const isKo = hero.isKo || hero.currentHp <= 0;

            this.ctx.beginPath();
            this.ctx.arc(x, y - 1, 16, 0, Math.PI * 2);
            this.ctx.fillStyle = 'rgba(0, 0, 0, 0.4)';
            this.ctx.fill();

            this.ctx.beginPath();
            this.ctx.arc(x, y - 2, 14, 0, Math.PI * 2);
            this.ctx.fillStyle = isKo ? '#334155' : (sideCode === 'A' ? '#dc2626' : '#2563eb');
            this.ctx.fill();
            this.ctx.strokeStyle = isKo ? '#64748b' : '#ffffff';
            this.ctx.lineWidth = 2;
            this.ctx.stroke();

            let initial = sideCode;
            if (hero.name) {
                const parts = hero.name.trim().split(/\s+/);
                if (parts.length >= 2) {
                    initial = (parts[0][0] + parts[1][0]).toUpperCase();
                } else {
                    initial = hero.name.slice(0, 3).toUpperCase();
                }
            }
            this.ctx.fillStyle = isKo ? '#94a3b8' : '#ffffff';
            this.ctx.font = 'bold 10px sans-serif';
            this.ctx.textAlign = 'center';
            this.ctx.textBaseline = 'middle';
            this.ctx.fillText(initial, x, y - 2);

            const slotShort = hero.slot ? hero.slot.replace('front_', 'F').replace('back_', 'B') : '';
            this.ctx.fillStyle = sideCode === 'A' ? '#f87171' : '#60a5fa';
            this.ctx.font = 'bold 8px monospace';
            this.ctx.fillText(slotShort, x, y - 17);

            const maxHp = hero.maxHp || 100;
            const currentHp = Math.max(0, hero.currentHp);
            const hpPct = Math.max(0, Math.min(1, currentHp / maxHp));

            this.ctx.fillStyle = '#0f172a';
            this.ctx.fillRect(x - 16, y + 13, 32, 5);

            if (!isKo && hpPct > 0) {
                this.ctx.fillStyle = hpPct > 0.5 ? '#22c55e' : (hpPct > 0.2 ? '#eab308' : '#ef4444');
                this.ctx.fillRect(x - 16, y + 13, 32 * hpPct, 5);
            }

            this.ctx.fillStyle = isKo ? '#ef4444' : '#cbd5e1';
            this.ctx.font = 'bold 8px monospace';
            this.ctx.fillText(isKo ? 'KO' : `${currentHp}/${maxHp}`, x, y + 24);
        });
    }
}
