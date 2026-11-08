const countdown = document.getElementById("event-countdown");
const finishedMessage = document.getElementById("countdown-finished");

if (countdown && finishedMessage) {
  const eventStart = new Date(countdown.dataset.eventStart);
  const values = {
    days: countdown.querySelector('[data-unit="days"]'),
    hours: countdown.querySelector('[data-unit="hours"]'),
    minutes: countdown.querySelector('[data-unit="minutes"]'),
    seconds: countdown.querySelector('[data-unit="seconds"]'),
  };

  function updateCountdown() {
    const remaining = eventStart.getTime() - Date.now();
    if (remaining <= 0) {
      countdown.classList.add("hidden");
      finishedMessage.classList.remove("hidden");
      return;
    }

    const totalSeconds = Math.floor(remaining / 1000);
    values.days.textContent = Math.floor(totalSeconds / 86400);
    values.hours.textContent = String(Math.floor((totalSeconds % 86400) / 3600)).padStart(2, "0");
    values.minutes.textContent = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, "0");
    values.seconds.textContent = String(totalSeconds % 60).padStart(2, "0");
  }

  updateCountdown();
  window.setInterval(updateCountdown, 1000);
}

function configureCompanions(groupSelect, attendanceSelect, wrapper, countInput) {
  if (!groupSelect || !attendanceSelect || !wrapper || !countInput) return;
  const update = () => {
    const needed = groupSelect.value === "accompanied" && attendanceSelect.value === "present";
    wrapper.classList.toggle("hidden", !needed);
    countInput.disabled = !needed;
    countInput.required = needed;
    if (!needed) countInput.value = "0";
    else if (Number(countInput.value) < 1) countInput.value = "1";
  };
  groupSelect.addEventListener("change", update);
  attendanceSelect.addEventListener("change", update);
  update();
}

configureCompanions(
  document.getElementById("id_group_type"),
  document.getElementById("id_attendance"),
  document.getElementById("companions-wrapper"),
  document.getElementById("id_companions_count"),
);
configureCompanions(
  document.getElementById("id_group_type"),
  document.getElementById("id_attendance"),
  document.getElementById("rsvp-companions-wrapper"),
  document.getElementById("id_companions_count"),
);

const intention = document.getElementById("id_intention");
const purchaseBox = document.getElementById("purchase-box");
const quantity = document.getElementById("id_cardigan_quantity");
const purchaseTotal = document.getElementById("purchase-total");

if (intention && purchaseBox && quantity && purchaseTotal) {
  const updatePurchase = () => {
    const buying = intention.value === "buy";
    purchaseBox.classList.toggle("hidden", !buying);
    quantity.disabled = !buying;
    quantity.required = buying;
    if (!buying) quantity.value = "0";
    else if (Number(quantity.value) < 1) quantity.value = "1";
    purchaseTotal.textContent = buying
      ? `Estimation indicative : ${(Number(quantity.value || 1) * 15000).toLocaleString("fr-FR")} F CFA`
      : "";
  };
  intention.addEventListener("change", updatePurchase);
  quantity.addEventListener("input", updatePurchase);
  updatePurchase();
}
