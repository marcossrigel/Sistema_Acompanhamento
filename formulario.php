<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<style>
:root {
    --color-white: #ffffff;
    --color-gray: #e3e8ec;
    --color-dark: #1d2129;
    --color-blue: #0a6be2;
    --color-green: #42b72a;
}

body {
  font-family: Arial, sans-serif;
  background-color: var(--color-gray);
  margin: 0;
  box-sizing: border-box;
  height: 100vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

main {
  flex: 1;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  padding: 0px;
  box-sizing: border-box;
  overflow-y: auto;
}

.pagina-formulario {
  display: flex;
  justify-content: center;
  width: 100%;
  padding: 40px 20px;
  box-sizing: border-box;
}

.formulario {
  background-color: white;
  padding: 40px;
  border-radius: 8px;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
  width: 1000px;
}

.main-title {
  text-align: center;
  font-size: 24px;
  font-weight: bold;
  margin-bottom: 20px;
}

.label {
  display: block;
  font-size: 14px;
  color: #333;
  margin-bottom: 4px;
  margin-left: 2px;
  font-weight: bold;
}

.linha {
  display: flex;
  gap: 15px;
  margin-bottom: 20px;
  flex-wrap: nowrap;
}

.campo-pequeno {
  flex: 1;
  min-width: 200px;
  display: flex;
  flex-direction: column;
}

.campo {
  flex: 1;
  min-width: 150px;
}

.campo-longo {
  flex: 2; 
  min-width: 200px;
}

.linha-atividade {
  display: flex;
  gap: 20px;
  margin-top: 20px;
  flex-wrap: wrap;
}

.coluna-textarea {
  flex: 2;
  display: flex;
  flex-direction: column;
}

.coluna-textarea textarea {
  width: 100%;
  box-sizing: border-box;
  height: 100px;
  padding: 10px;
  border-radius: 8px;
  border: 1px solid #ccc;
  resize: vertical;
  font-size: 14px;
  font-family: inherit;
}

button.btn {
  background-color: var(--color-green);
  color: var(--color-white);
  text-decoration: none;
  padding: 14px 20px;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 600;
  text-align: center;
  transition: background 0.3s;
  width: 200px;
  display: inline-block;
  border: none;
  outline: none;
  cursor: pointer;
  margin: 20px auto;   
  display: block;
}

.btn-remover {
  background: none;
  border: none;
  color: red;
  font-size: 16px;
  cursor: pointer;
  margin-left: 8px;
}
.btn-remover:hover {
  opacity: 0.7;
}

.lista-compartilhados li {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 0.5rem;
}

.lista-compartilhados li button.btn-remover {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 18px;
  margin-left: auto;
  padding: 0;
  color: #444;
}

.lista-compartilhados li button.btn-remover:hover {
  color: red;
  transform: scale(1.1);
}


button.btn:hover {
  background-color: #36a420;
}

.texto-login {
  text-align: center;
  color: var(--color-blue);
  font-size: 14px;
  margin-top: 20px auto;
  display: block;
}

a.texto-login{
  color: red;               
  text-decoration: none;  
  font-weight: bold;
}

a.texto-login:hover {
  text-decoration: none;
}

input[type="text"],
input[type="date"],
input[type="time"],
input[list],
select {
  width: 100%;
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 6px;
  box-sizing: border-box;
  font-size: 14px;
}

.modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0,0,0,0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
}

.modal-content {
  background-color: white;
  padding: 30px 40px;
  border-radius: 10px;
  text-align: center;
  max-width: 400px;
  width: 90%;
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
  word-wrap: break-word;
}

#modal-message {
  font-size: 16px;
  margin-bottom: 20px;
  color: #333;
  font-weight: bold;
}

.hidden {
  display: none;
}

.modal-content button {
  margin-top: 20px;
  padding: 10px 20px;
  border: none;
  background-color:#42b72a;
  color: white;
  border-radius: 6px;
  cursor: pointer;
}

body.solicitar-acesso main {
  align-items: center;
  min-height: 100vh;
  padding: 0;
}

body.solicitar-acesso .formulario-container {
  max-width: 400px;
  margin: auto;
}

.modal-content button:hover {
  background-color: #42b72a;
}

.lista-compartilhados {
  list-style: none;
  padding: 0;
  margin-top: 1rem;
}

.lista-compartilhados li {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 0.5rem;
}

.icone-usuario {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
}

@media (max-width: 768px) {
  .formulario {
    width: 100%;
    padding: 15px;
  }

  .linha {
    flex-direction: column;
    gap: 10px;
  }

  .linha-atividade {
    flex-direction: column;
  }

  .campo,
  .campo-pequeno,
  .campo-longo,
  .coluna-textarea {
    width: 100%;
    min-width: 100%;
  }

  textarea {
    width: 100% !important;
    box-sizing: border-box;
  }

  button.btn {
    width: 100%;
  }

  .main-title {
    font-size: 20px;
  }
}
</style>

<body>

<div class="pagina-formulario">
<form class="formulario" action="processa_formulario.php" method="post">
  <h1 class="main-title">Nova Solicitação</h1>

  <div class="linha">

  <div class="campo-longo">
    <label class="label">Demanda</label>
    <input type="text" name="demanda" class="campo" placeholder="Descrição da demanda">
  </div>

    <div class="campo-pequeno">
      <label class="label">SEI</label>
      <input type="text" name="sei" class="campo" required placeholder="Ex: 1234567.000000/2025-00">
    </div>

    <div class="campo-pequeno">
      <label class="label">Código</label>
      <input type="text" name="codigo" class="campo" required placeholder="Ex: 001/2025">
    </div>

    <div class="campo-pequeno">
      <label class="label">Setor</label>
      <select name="setor" class="campo" required>
        <option value="">Selecione...</option>
        <option>Gabinete</option>
        <option>DAF</option>
        <option>Gecomp</option>
        <option>GOP</option>
        <option>CPL</option>
        <option>DAF</option>
        <option>Jurídico</option>
        <option>Gefin</option>
      </select>
    </div>
  </div>

  <div class="linha">
    <div class="campo-pequeno">
      <label class="label">Responsável</label>
      <input type="text" name="responsavel" class="campo" required placeholder="Nome do responsável">
    </div>

    <div class="campo-pequeno">
      <label class="label">Data Solicitação</label>
      <input type="date" name="data_solicitacao" class="campo" required>
    </div>

    <div class="campo-pequeno">
      <label class="label">Data Liberação</label>
      <input type="date" name="data_liberacao" class="campo">
    </div>
  </div>

  <div class="linha">
    <div class="campo-pequeno">
      <label class="label">Tempo Médio (hh:mm)</label>
      <input type="time" name="tempo_medio" class="campo">
    </div>

    <div class="campo-pequeno">
      <label class="label">Tempo Real (Data)</label>
      <input type="date" name="tempo_real" class="campo">
    </div>
  </div>

  <button type="submit" class="btn btn-create-account">Salvar</button>
  <a href="home.php" class="texto-login" onclick="confirmarCancelamento(event)">Cancelar</a>

</form>

</div>
</body>
</html>