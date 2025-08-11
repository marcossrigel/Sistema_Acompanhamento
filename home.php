<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sistemas - Portal CEHAB</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{
    --bg:#edf3f7;
    --card:#fff;
    --text:#1d2129;
    --muted:#6b7280;
    --shadow:0 8px 20px rgba(0,0,0,.08);
    --primary:#2563eb;
    --danger:#ef4444;
}
*{box-sizing:border-box;margin:0;padding:0}
body{
    font-family:'Poppins',sans-serif;
    background:var(--bg);
    min-height:100vh;
    display:flex;align-items:center;justify-content:center;
    color:var(--text);
}
.wrap{
    width:100%;
    max-width:1100px;
    padding:32px 20px 48px;
    display:flex;flex-direction:column;align-items:center;
    gap:28px;
}
.header{
    width:100%;
    display:flex;align-items:center;justify-content:space-between;
    gap:12px;
}
.hello{
    font-weight:600;font-size:18px;color:var(--muted)
}
.title{
    width:100%;
    text-align:center;
    font-weight:700;font-size:28px;
}
.grid{
    width:100%;
    display:grid;
    grid-template-columns: repeat(6, 1fr);
    gap:22px;
    justify-items:center;
  }

@media (max-width:1200px){ .grid{grid-template-columns: repeat(4, 1fr);} }
@media (max-width:900px){ .grid{grid-template-columns: repeat(3, 1fr);} }
@media (max-width:640px){ .grid{grid-template-columns: repeat(2, 1fr);} }
@media (max-width:420px){ .grid{grid-template-columns: 1fr;} }

.card {
    width: 220px; /* antes era 180px */
    height: 140px;
    background: var(--card);
    border-radius: 16px;
    box-shadow: var(--shadow);
    padding: 18px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    border: 1px solid rgba(0,0,0,.04);
    }
.card:hover, .card:focus{
    transform:translateY(-2px);
    box-shadow:0 12px 28px rgba(0,0,0,.12);
    outline:none;
    }
.card .icon{
    font-size:32px; line-height:1; margin-bottom:12px;
}
    .card .name{
    text-align:center; font-weight:700; font-size:18px; color:var(--text);
}
.card .desc{
    margin-top:6px; font-size:12px; color:var(--muted); text-align:center;
}

.logout{
    border:0; cursor:pointer; padding:10px 16px;
    border-radius:999px; color:#fff; background:var(--danger);
    font-weight:700; box-shadow:var(--shadow);
    transition:filter .15s ease;
}
.logout:hover{ filter:brightness(.95); }
.footer{
    margin-top:6px; display:flex; gap:10px; align-items:center;
}

.wrap {
    width: 100%;
    max-width: 800px;
    padding: 32px 20px 48px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 28px;
    min-height: 100vh; 
}

.grid {
  display: flex;
  justify-content: center;
  gap: 22px;
  flex-wrap: wrap;
}

.footer {
  margin-top: 20px;
}

.logout {
  border: 0;
  cursor: pointer;
  padding: 10px 16px;
  border-radius: 999px;
  color: #fff;
  background: var(--danger);
  font-weight: 700;
  box-shadow: var(--shadow);
  transition: filter .15s ease;
}
.logout:hover {
  filter: brightness(.95);
}

</style>
</head>
<body>
    
    <main class="wrap">
    <h1 class="title">Olá $Nome_Usuario 👋</h1>
    <h1 class="title">Seja Bem-Vindo ao Acompanhamento SEI</h1>

    <section class="grid" aria-label="Lista de sistemas">
        <a class="card" href="formulario.php">
            <div class="icon">🧾</div>
            <div class="name">Nova Demanda</div>
            <div class="desc">Inicie um novo processo</div>
        </a>

        <a class="card" href="acompanhamento.php">
            <div class="icon">🔍</div>
            <div class="name">Acompanhamento</div>
            <div class="desc">Status e Atualizações</div>
        </a>
    </section>

    <div class="footer">
        <a href="https://www.getic.pe.gov.br/?p=home" class="logout">Sair</a>
    </div>
    </main>
</body>
</html>