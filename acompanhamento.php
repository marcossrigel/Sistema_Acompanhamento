<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acompanhamento — Portal CEHAB</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="./assets/css/acompanhamento.css">

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
