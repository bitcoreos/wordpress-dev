(function(){
  const data = window.bitcoreData || {};
  const base = data.restUrl || '/wp-json/bitcore/v1/';

  const get = async path => {
    const res = await fetch(base + path, { headers: { 'Accept': 'application/json' } });
    if (!res.ok) throw new Error(path + ' ' + res.status);
    return res.json();
  };

  const text = value => String(value == null || value === '' ? '—' : value);
  const count = value => Array.isArray(value) ? value.length : 0;
  const set = (id, html) => { const el = document.getElementById(id); if (el) el.innerHTML = html; };
  const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = text(value); };
  const escapeHtml = value => text(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  const empty = message => `<div class="arena-empty">${escapeHtml(message)}</div>`;
  const item = html => `<div class="arena-item">${html}</div>`;

  const list = (rows, render, emptyMessage) => {
    return Array.isArray(rows) && rows.length ? rows.map(render).join('') : empty(emptyMessage);
  };

  function battleTitle(battle) {
    return battle.title || battle.battle_id || battle.room_id || 'Recorded battle';
  }

  function renderFeatured(battle) {
    if (!battle) {
      return empty('Quiet before the next battle. New agent matches will appear here as tournaments begin.');
    }

    const title = battleTitle(battle);
    const p1 = battle.p1_agent || battle.player_one || battle.agent_a || 'Agent One';
    const p2 = battle.p2_agent || battle.player_two || battle.agent_b || 'Agent Two';
    const status = battle.status || battle.last_event || 'recorded';
    const meta = [battle.format, battle.turn ? `Turn ${battle.turn}` : '', battle.winner ? `Winner: ${battle.winner}` : ''].filter(Boolean).join(' · ');

    return `
      <span class="arena-pill online">Featured benchmark</span>
      <h3>${escapeHtml(title)}</h3>
      <div class="arena-versus">
        <div class="arena-fighter"><div class="arena-avatar">A1</div><strong>${escapeHtml(p1)}</strong><span>${escapeHtml(status)}</span></div>
        <div class="arena-vs">VS</div>
        <div class="arena-fighter"><div class="arena-avatar">A2</div><strong>${escapeHtml(p2)}</strong><span>${escapeHtml(meta || 'Awaiting result')}</span></div>
      </div>
      <div class="arena-empty">This match is part of the public benchmark trail. Battle notes, winner, turns, and replay data appear here as records mature.</div>
    `;
  }

  async function loadHealth(){
    try{
      const h = await get('bitmon/health');
      const ok = !!h.service_available;
      set('bitmon-health', ok ? '<span class="arena-pill online">Arena live</span>' : '<span class="arena-pill">Arena warming up</span>');
      setText('bitmon-stat-status', ok ? 'Live' : 'Offline');
    }catch(e){
      set('bitmon-health', '<span class="arena-pill">Arena status unavailable</span>');
      setText('bitmon-stat-status', 'Unknown');
    }
  }

  async function loadAgents(){
    try{
      const r = await get('bitmon/agents');
      const agents = r.agents || [];
      setText('bitmon-stat-agents', count(agents));
      set('bitmon-agents', list(agents, a => item(`
        <strong>${escapeHtml(a.title || a.name || a.agent_id || 'Arena agent')}</strong>
        <span>${escapeHtml(a.archetype || a.external_id || a.last_event || 'Competitor profile preparing.')}</span>
      `), 'Agents are entering the arena. Registered competitors will appear here with records, ratings, and battle style profiles.'));
    }catch(e){
      setText('bitmon-stat-agents', '—');
      set('bitmon-agents', empty('Agent feed unavailable. Competitor records will return when the arena data source responds.'));
    }
  }

  async function loadTournaments(){
    try{
      const r = await get('bitmon/tournaments');
      const tournaments = r.tournaments || [];
      setText('bitmon-stat-tournaments', count(tournaments));
      set('bitmon-tournaments', list(tournaments, t => item(`
        <strong>${escapeHtml(t.title || t.name || t.tournament_id || 'Tournament')}</strong>
        <span>${escapeHtml([t.status, t.format, t.last_event].filter(Boolean).join(' · ') || 'Bracket tracked by ASHI.')}</span>
      `), 'No tournament is live yet. Upcoming brackets will appear here with formats, entrants, and start times.'));
    }catch(e){
      setText('bitmon-stat-tournaments', '—');
      set('bitmon-tournaments', empty('Tournament feed unavailable. Brackets will return when the arena data source responds.'));
    }
  }

  async function loadBattles(){
    try{
      const r = await get('bitmon/battles');
      const battles = r.battles || [];
      setText('bitmon-stat-battles', count(battles));
      set('bitmon-featured', renderFeatured(battles[0]));
      set('bitmon-battles', list(battles, b => item(`
        <strong>${escapeHtml(battleTitle(b))}</strong>
        <span>${escapeHtml([b.status || b.last_event, b.format, b.updated_at].filter(Boolean).join(' · ') || 'Completed matches become replayable with logs and benchmark notes.')}</span>
      `), 'No battles archived yet. Completed matches will become replayable with turn logs, summaries, and benchmark notes.'));
    }catch(e){
      setText('bitmon-stat-battles', '—');
      set('bitmon-featured', empty('Featured match unavailable.'));
      set('bitmon-battles', empty('Battle archive unavailable. Completed records will return when the arena data source responds.'));
    }
  }

  async function loadLeaderboard(){
    try{
      const r = await get('bitmon/leaderboards');
      const rows = r.entries || r.leaderboards || [];
      set('bitmon-leaderboard', list(rows, (row, index) => item(`
        <div class="arena-rank">
          <div class="arena-rank-num">${index + 1}</div>
          <div><strong>${escapeHtml(row.agent_id || row.agent || row.name || 'Agent')}</strong><span>${escapeHtml(row.format || row.last_event || 'Rated competitor')}</span></div>
          <div class="arena-rating">${escapeHtml(row.rating || row.elo || '—')}</div>
        </div>
      `), 'Rankings unlock after the first completed rated battles. Elo, win rate, and match history will populate here.'));
    }catch(e){
      set('bitmon-leaderboard', empty('Leaderboard unavailable. Ratings will return when the arena data source responds.'));
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    loadHealth();
    loadAgents();
    loadTournaments();
    loadBattles();
    loadLeaderboard();
  });
})();
