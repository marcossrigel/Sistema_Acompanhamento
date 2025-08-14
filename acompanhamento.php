<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Acompanhamento — Portal CEHAB</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#eef3f7;
    --ink:#223;
    --muted:#6b7280;
    --done:#16a34a;
    --doing:#f59e0b;
    --todo:#9ca3af;
    --card:#fff;
    --rail:#e5e7eb;
    --rail-fill:#2563eb;
    --shadow:0 8px 24px rgba(0,0,0,.06);
    --radius:18px;
  }
  *{box-sizing:border-box}
  body{
    margin:0; font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial;
    background:var(--bg); color:var(--ink);
  }
  .wrap{max-width:1080px; margin:24px auto; padding:0 16px;}
  .title{font-size:clamp(22px,2.8vw,34px); text-align:center; margin:6px 0 2px}
  .subtitle{ text-align:center; color:var(--muted); margin-bottom:22px}
  .subtitle strong{color:var(--doing)}

  .timeline{
    background:var(--card); border-radius:24px; box-shadow:var(--shadow);
    padding:22px; overflow:hidden;
  }

  /* Cabeçalho de legenda */
  .legend{display:flex; gap:18px; align-items:center; justify-content:center; margin-bottom:14px}
  .legend span{display:inline-flex; gap:8px; align-items:center; color:var(--muted); font-size:13px}
  .legend i{display:inline-block; width:10px; height:10px; border-radius:999px}
  .lg-done{background:var(--done)}
  .lg-current{background:var(--doing)}
  .lg-todo{background:var(--todo)}

  /* Grade de passos (quebra sem rolagem) */
  .grid{
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap:18px;
    position:relative;
    padding-top:24px; /* espaço p/ trilho */
  }

  /* Trilho + progresso */
  .rail{
    position:absolute; left:0; right:0; top:4px; height:8px; border-radius:999px;
    background:var(--rail);
  }
  .progress{
    height:100%; width:0%;
    background:linear-gradient(90deg, var(--rail-fill), #3b82f6);
    border-radius:999px; transition:width .35s ease;
  }

  .step{
    border:1px solid #eef; border-radius:var(--radius);
    padding:16px 16px 18px; background:#fff; box-shadow:0 1px 0 rgba(0,0,0,.03);
  }
  .step h4{margin:0 0 6px; font-size:16px}
  .step small{display:block; color:var(--muted); margin-bottom:10px}

  .pill{
    display:inline-block; font-weight:600; font-size:12px; letter-spacing:.3px;
    padding:8px 12px; border-radius:999px;
  }
  .done   .pill{background:rgba(22,163,74,.12); color:var(--done)}
  .current .pill{background:rgba(245,158,11,.14); color:var(--doing)}
  .todo   .pill{background:rgba(156,163,175,.16); color:var(--todo)}
  
  /* Indicador (checado / corrente / pendente) */
  .dots{margin-top:12px; display:flex; align-items:center; gap:6px}
  .dot{
    width:20px; height:20px; border-radius:999px; display:grid; place-items:center;
    font-size:12px; border:2px solid currentColor;
  }
  .done   .dot{color:var(--done); background:rgba(22,163,74,.07)}
  .current .dot{color:var(--doing); background:rgba(245,158,11,.08)}
  .todo   .dot{color:var(--todo); background:transparent}

  /* Rodapé */
  .footer{display:flex; justify-content:center; margin:18px 0 6px}
  .btn-back{
    text-decoration:none; background:#1e40af; color:#fff; padding:10px 18px; border-radius:12px;
    box-shadow:var(--shadow);
  }
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

      <!-- TRILHO + PASSOS (sem rolagem) -->
      <div class="grid" id="flow">
        <div class="rail"><div class="progress" id="progress"></div></div>

        <!-- Passe “done | current | todo” conforme o status de cada etapa -->
        <article class="step done">
          <h4>Demandante</h4>
          <small>Recebido • 02/08/2025</small>
          <span class="pill">CONCLUÍDO</span>
          <div class="dots"><div class="dot">✓</div></div>
        </article>

        <article class="step current">
          <h4>DAF</h4>
          <small>Recebido • 02/08/2025</small>
          <span class="pill">EM ANDAMENTO</span>
          <div class="dots"><div class="dot">✓</div></div>
        </article>

        <article class="step todo">
          <h4>GECOMP</h4>
          <small>Análise • 04/08/2025</small>
          <span class="pill">PENDENTE</span>
          <div class="dots"><div class="dot">•</div></div>
        </article>

        <article class="step todo">
          <h4>DDO</h4>
          <small>Execução • 11/08/2025</small>
          <span class="pill">PENDENTE</span>
          <div class="dots"><div class="dot">•</div></div>
        </article>

        <article class="step todo">
          <h4>CPL</h4>
          <small>Aguardando • —</small>
          <span class="pill">PENDENTE</span>
          <div class="dots"><div class="dot">•</div></div>
        </article>

        <article class="step todo">
          <h4>Homologação</h4>
          <small>Aguardando • —</small>
          <span class="pill">PENDENTE</span>
          <div class="dots"><div class="dot">•</div></div>
        </article>

        <article class="step todo">
          <h4>Parecer Jur.</h4>
          <small>Aguardando • —</small>
          <span class="pill">PENDENTE</span>
          <div class="dots"><div class="dot">•</div></div>
        </article>

        <article class="step todo">
          <h4>NE</h4>
          <small>Aguardando • —</small>
          <span class="pill">PENDENTE</span>
          <div class="dots"><div class="dot">•</div></div>
        </article>

        <article class="step todo">
          <h4>PF</h4>
          <small>Aguardando • —</small>
          <span class="pill">PENDENTE</span>
          <div class="dots"><div class="dot">•</div></div>
        </article>

        <article class="step todo">
          <h4>NE</h4>
          <small>Aguardando • —</small>
          <span class="pill">PENDENTE</span>
          <div class="dots"><div class="dot">•</div></div>
        </article>

        <article class="step todo">
          <h4>LIQ</h4>
          <small>Aguardando • —</small>
          <span class="pill">PENDENTE</span>
          <div class="dots"><div class="dot">•</div></div>
        </article>

        <article class="step todo">
          <h4>PD</h4>
          <small>Aguardando • —</small>
          <span class="pill">PENDENTE</span>
          <div class="dots"><div class="dot">•</div></div>
        </article>

        <article class="step todo">
          <h4>OB</h4>
          <small>Aguardando • —</small>
          <span class="pill">PENDENTE</span>
          <div class="dots"><div class="dot">•</div></div>
        </article>

        <article class="step todo">
          <h4>Remessa</h4>
          <small>Aguardando • —</small>
          <span class="pill">PENDENTE</span>
          <div class="dots"><div class="dot">•</div></div>
        </article>

      </div>
    </section>

    <div class="footer">
      <a href="visualizar.php" class="btn-back">‹ Voltar</a>
    </div>
  </main>

<script>
  // Preenche a linha de progresso até a etapa “current”
  (function(){
    const flow = document.getElementById('flow');
    const progress = document.getElementById('progress');
    const steps = [...flow.querySelectorAll('.step')];

    const currentIndex = steps.findIndex(s => s.classList.contains('current'));
    const lastDoneIdx = Math.max(...steps.map((s,i)=> s.classList.contains('done') ? i : -1));
    const idx = currentIndex >= 0 ? currentIndex : lastDoneIdx;

    // calcula porcentagem baseada na posição dos cartões dentro da grade (sem rolagem)
    if (idx >= 0){
      const first = steps[0].getBoundingClientRect();
      const target = steps[idx].getBoundingClientRect();
      const flowRect = flow.getBoundingClientRect();

      const startX = first.left + first.width/2 - flowRect.left;
      const endX   = target.left + target.width/2 - flowRect.left;
      const pct = Math.max(0, Math.min(100, (endX - startX) / flowRect.width * 100));
      progress.style.width = pct + '%';
    } else {
      progress.style.width = '0%';
    }

    // recalc em resize
    let t;
    window.addEventListener('resize', () => {
      clearTimeout(t);
      t = setTimeout(()=>{ 
        const ev = new Event('load'); window.dispatchEvent(ev);
      }, 120);
    });
    window.addEventListener('load', ()=>{}); // só p/ compatibilidade com o timeout acima
  })();
</script>
</body>
</html>
