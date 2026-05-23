document.addEventListener("DOMContentLoaded", () => {
  const timerEl = document.querySelector("[data-timer]");
  const progressBar = document.querySelector("[data-progress-bar]");
  const timeOutput = document.querySelector("[data-time-output]");
  const examForm = document.querySelector("#examForm");

  if (progressBar) {
    const radios = [...document.querySelectorAll(".answer-grid input[type='radio']")];
    const updateProgress = () => {
      const total = document.querySelectorAll("[data-question]").length;
      const answered = new Set(radios.filter((radio) => radio.checked).map((radio) => radio.name)).size;
      const pct = total ? Math.round((answered / total) * 100) : 0;
      progressBar.style.width = `${pct}%`;
      progressBar.textContent = `${answered}/${total}`;
    };
    radios.forEach((radio) => radio.addEventListener("change", updateProgress));
    updateProgress();
  }

  if (timerEl && examForm && timeOutput) {
    let seconds = 0;
    const limitMin = Number(timerEl.dataset.limit || 0);
    let remaining = limitMin ? limitMin * 60 : 0;
    const render = () => {
      const current = limitMin ? remaining : seconds;
      const mins = String(Math.floor(current / 60)).padStart(2, "0");
      const secs = String(current % 60).padStart(2, "0");
      timerEl.textContent = `${mins}:${secs}`;
      timeOutput.value = seconds;
    };
    render();
    const interval = setInterval(() => {
      seconds += 1;
      if (limitMin) {
        remaining -= 1;
        if (remaining <= 0) {
          clearInterval(interval);
          alert("El tiempo ha finalizado. La prueba se enviará automáticamente.");
          examForm.submit();
          return;
        }
      }
      render();
    }, 1000);
  }

  document.querySelectorAll(".latex-insert").forEach((button) => {
    button.addEventListener("click", () => {
      const target = document.getElementById(button.dataset.target);
      if (!target) return;
      target.value += `$${button.dataset.snippet}$`;
      target.dispatchEvent(new Event("input"));
    });
  });

  document.querySelectorAll(".math-live").forEach((field) => {
    const preview = document.querySelector(`[data-preview='${field.id}']`);
    if (!preview) return;
    const sync = () => {
      preview.innerHTML = field.value || "Vista previa de LaTeX";
      if (window.MathJax?.typesetPromise) window.MathJax.typesetPromise([preview]);
    };
    field.addEventListener("input", sync);
    sync();
  });

  const correctaInput = document.querySelector("#correctaInput");
  document.querySelectorAll(".option-picker").forEach((input) => {
    input.addEventListener("dblclick", () => {
      document.querySelectorAll(".option-picker").forEach((item) => item.classList.remove("correct-option"));
      input.classList.add("correct-option");
      if (correctaInput) correctaInput.value = input.dataset.option;
    });
  });
});
