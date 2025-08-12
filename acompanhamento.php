<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acompanhamento — Portal CEHAB</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>

:root{
  --bg:#edf3f7; --card:#fff; --text:#1d2129; --muted:#6b7280;
  --shadow:0 8px 20px rgba(0,0,0,.08);
  --primary:#2563eb; --success:#16a34a; --warn:#f59e0b; --line:#e5e7eb;
  --danger:#ef4444;
}

*{
  box-sizing:border-box;
  margin:0;
  padding:0
}

body{
  font-family:'Poppins',sans-serif; background:var(--bg); color:var(--text);
  min-height:100vh; display:flex; align-items:center; justify-content:center;
}

.wrap{ width:100%; 
  max-width:1100px; 
  padding:28px 20px 42px; 
  display:flex; 
  flex-direction:column; 
  gap:18px; 
}

.title{ 
  text-align:center; 
  font-weight:700; 
  font-size:28px; 
}  

.subtitle{ 
  text-align:center; 
  color:var(--muted); 
  font-weight:600; 
}

.timeline{
  background:var(--card);
  border-radius:16px;
  box-shadow:var(--shadow);
  padding:100px;
  border:1px solid #eef2f7;
}

.flow{
  overflow-x:auto; padding-bottom:10px;
}
.rail{
  display:flex; align-items:flex-start; gap:28px; min-width:760px; padding:8px 4px;
  position:relative;
}

.step{
  width:200px; min-width:200px;
  background:#fff; border:2px solid #e6ebf0; border-radius:12px; padding:14px;
  text-align:center; position:relative;
  transition:transform .15s ease, box-shadow .15s ease, border-color .15s ease;
}
.step:hover{ transform:translateY(-2px); box-shadow:0 10px 20px rgba(0,0,0,.06); }
.step h4{ font-size:15px; font-weight:700; margin-bottom:6px; }
.step small{ color:var(--muted); display:block; }
.pill{
  margin-top:10px; display:inline-block; padding:6px 10px; border-radius:999px;
  font-size:12px; font-weight:700; color:#fff;
}
.done .pill{ background:var(--success); }
.current .pill{ background:var(--warn); }
.todo .pill{ background:#9ca3af; }

.dot{
  width:28px; height:28px; border-radius:999px; background:#fff; border:3px solid #9ca3af;
  position:absolute; left:50%; transform:translateX(-50%); bottom:-36px; z-index:2;
  display:flex; align-items:center; justify-content:center; font-weight:700; color:#9ca3af;
}
.done .dot{ border-color:var(--success); color:var(--success); }
.current .dot{ border-color:var(--warn); color:var(--warn); }

.connector{
  position:absolute; left:0; right:0; height:6px; background:var(--line); bottom:-25px; z-index:1;
}
.progress{
  position:absolute; left:0; height:6px; background:var(--success); bottom:-25px; z-index:1;
  width:0%;
}

.legend{ display:flex; gap:12px; align-items:center; justify-content:center; color:var(--muted); font-size:13px; }
.legend span{ display:inline-flex; align-items:center; gap:6px; }
.legend i{ width:10px; height:10px; border-radius:2px; display:inline-block; }
.legend .lg-done{ background:var(--success); }
.legend .lg-current{ background:var(--warn); }
.legend .lg-todo{ background:#9ca3af; }
.footer{ margin-top:10px; text-align:center; }
.btn-back{
  display:inline-block; text-decoration:none; text-align:center;
  color:#fff; background:var(--primary);
  padding:10px 16px; border-radius:999px; font-weight:700;
  box-shadow:var(--shadow); transition:filter .15s ease;
}
.btn-back:hover{ filter:brightness(.95); }

@media (max-width:720px){ .title{font-size:22px} }
</style>
</head>
<body>
  <main class="wrap">
    <h1 class="title">Informações sobre o último serviço</h1>
    <div class="subtitle">Situação: <strong>EM ANDAMENTO</strong></div>

    <section class="timeline">
      <div class="legend" aria-hidden="true">
        <span><i class="lg-done"></i> Concluído</span>
        <span><i class="lg-current"></i> Em andamento</span>
        <span><i class="lg-todo"></i> Pendente</span>
      </div>

      <div class="flow">
        <div class="rail" id="rail">
          <div class="connector"></div>
          <div class="progress" id="progress"></div>

          <div class="step done">
            <h4>DAF</h4>
            <small>Recebido • 02/08/2025</small>
            <div class="pill">CONCLUÍDO</div>
            <div class="dot">✓</div>
          </div>

          <div class="step done">
            <h4>GECOMP</h4>
            <small>Análise • 04/08/2025</small>
            <div class="pill">CONCLUÍDO</div>
            <div class="dot">✓</div>
          </div>

          <div class="step current">
            <h4>DDO</h4>
            <small>Execução • 11/08/2025</small>
            <div class="pill">EM ANDAMENTO</div>
            <div class="dot">•</div>
          </div>

          <div class="step todo">
            <h4>CPL</h4>
            <small>Aguardando • —</small>
            <div class="pill">PENDENTE</div>
            <div class="dot">•</div>
          </div>

          <div class="step todo">
            <h4>Licitacao</h4>
            <small>Aguardando • —</small>
            <div class="pill">PENDENTE</div>
            <div class="dot">•</div>
          </div>

          <div class="step todo">
            <h4>Homologação</h4>
            <small>Aguardando • —</small>
            <div class="pill">PENDENTE</div>
            <div class="dot">•</div>
          </div>

          <div class="step todo">
            <h4>Parecer Jur.</h4>
            <small>Aguardando • —</small>
            <div class="pill">PENDENTE</div>
            <div class="dot">•</div>
          </div>

          <div class="step todo">
            <h4>NE</h4>
            <small>Aguardando • —</small>
            <div class="pill">PENDENTE</div>
            <div class="dot">•</div>
          </div>

          <div class="step todo">
            <h4>PF</h4>
            <small>Aguardando • —</small>
            <div class="pill">PENDENTE</div>
            <div class="dot">•</div>
          </div>

          <div class="step todo">
            <h4>LIQ</h4>
            <small>Aguardando • —</small>
            <div class="pill">PENDENTE</div>
            <div class="dot">•</div>
          </div>

          <div class="step todo">
            <h4>PD</h4>
            <small>Aguardando • —</small>
            <div class="pill">PENDENTE</div>
            <div class="dot">•</div>
          </div>

          <div class="step todo">
            <h4>OB</h4>
            <small>Aguardando • —</small>
            <div class="pill">PENDENTE</div>
            <div class="dot">•</div>
          </div>

          <div class="step todo">
            <h4>Remessa</h4>
            <small>Aguardando • —</small>
            <div class="pill">PENDENTE</div>
            <div class="dot">•</div>
          </div>
        </div>
      </div>
    </section>

    <div class="footer">
      <a href="visualizar.php" class="btn-back">&lt; Voltar</a>
    </div>
  </main>

<script>
  (function(){
    const rail = document.getElementById('rail');
    const progress = document.getElementById('progress');
    const steps = Array.from(rail.querySelectorAll('.step'));
    const idxCurrent = steps.findIndex(s => s.classList.contains('current'));
    const lastDoneIdx = Math.max(...steps.map((s,i)=>s.classList.contains('done')?i:-1));
    const targetIdx = idxCurrent >= 0 ? idxCurrent : lastDoneIdx;

    if (targetIdx >= 0) {
      const first = steps[0].getBoundingClientRect();
      const target = steps[targetIdx].getBoundingClientRect();
      const railRect = rail.getBoundingClientRect();
      const startX = first.left + first.width/2 - railRect.left;
      const endX   = target.left + target.width/2 - railRect.left;
      progress.style.width = Math.max(0, endX - startX) + 'px';
    } else {
      progress.style.width = '0px';
    }
  })();
</script>

</body>
</html>
