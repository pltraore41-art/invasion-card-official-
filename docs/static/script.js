const countdown = document.getElementById("event-countdown");
const finishedMessage = document.getElementById("countdown-finished");

if (countdown && finishedMessage) {
  const eventStart = new Date(countdown.dataset.eventStart);
  const countdownValues = {
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
    countdownValues.days.textContent = Math.floor(totalSeconds / 86400);
    countdownValues.hours.textContent = String(
      Math.floor((totalSeconds % 86400) / 3600),
    ).padStart(2, "0");
    countdownValues.minutes.textContent = String(
      Math.floor((totalSeconds % 3600) / 60),
    ).padStart(2, "0");
    countdownValues.seconds.textContent = String(totalSeconds % 60).padStart(
      2,
      "0",
    );
  }

  updateCountdown();
  window.setInterval(updateCountdown, 1000);
}

const STORAGE_KEY = "invasion-cardigan-registrations-v1";
const attendanceLabels = {
  present: "Oui, je confirme ma présence",
  absent: "Non, je ne pourrai pas venir",
  unsure: "Je ne suis pas encore sûr(e)",
};
const beverageLabels = {
  alcohol: "Boissons alcoolisées",
  non_alcoholic: "Boissons non alcoolisées",
  sweet: "Boissons non alcoolisées",
  both: "Boissons alcoolisées et non alcoolisées",
  none: "Aucune préférence",
};
const knowledgeLabels = {
  known: "Connaît déjà le cardigan",
  heard: "En a déjà entendu parler",
  new: "Découvre le cardigan",
};
const intentionLabels = {
  buy: "Acheter un cardigan — 15 000 F CFA l'unité",
  discover: "Découvrir la collection",
  inform: "Se renseigner avant un achat",
  accompany: "Accompagner un proche",
};

function readRegistrations() {
  const raw = window.localStorage.getItem(STORAGE_KEY);
  if (raw === null) return [];
  const registrations = JSON.parse(raw);
  if (!Array.isArray(registrations)) {
    throw new Error("Les inscriptions enregistrées dans ce navigateur sont illisibles.");
  }
  return registrations;
}

function saveRegistrations(registrations) {
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(registrations));
}

function showStatus(element, message, isError = false) {
  element.textContent = message;
  element.classList.toggle("status-error", isError);
}

function formValues(form) {
  return Object.fromEntries(new FormData(form).entries());
}

function createReference(registrations) {
  let reference;
  do {
    const bytes = new Uint8Array(5);
    window.crypto.getRandomValues(bytes);
    reference = `INV-${Array.from(bytes, (byte) => byte.toString(16).padStart(2, "0"))
      .join("")
      .toUpperCase()}`;
  } while (registrations.some((registration) => registration.reference === reference));
  return reference;
}

const registrationForm = document.getElementById("registration-form");
const registrationStatus = document.getElementById("registration-status");

function configureCompanions(groupSelect, attendanceSelect, wrapper, countInput) {
  if (!groupSelect || !attendanceSelect || !wrapper || !countInput) return;
  const update = () => {
    const needsCount = groupSelect.value === "accompanied" && attendanceSelect.value === "present";
    wrapper.classList.toggle("hidden", !needsCount);
    countInput.disabled = !needsCount;
    countInput.required = needsCount;
    if (!needsCount) countInput.value = "0";
    else if (Number(countInput.value) < 1) countInput.value = "1";
  };
  groupSelect.addEventListener("change", update);
  attendanceSelect.addEventListener("change", update);
  update();
}

function configurePurchaseQuantity(intentionSelect, purchaseBox, quantityInput, totalLabel) {
  if (!intentionSelect || !purchaseBox || !quantityInput || !totalLabel) return;
  const update = () => {
    const buying = intentionSelect.value === "buy";
    purchaseBox.classList.toggle("hidden", !buying);
    quantityInput.disabled = !buying;
    quantityInput.required = buying;
    if (buying && Number(quantityInput.value) < 1) quantityInput.value = "1";
    if (!buying) quantityInput.value = "0";
    const quantity = buying ? Number(quantityInput.value || 1) : 0;
    totalLabel.textContent = buying
      ? `Estimation indicative : ${(quantity * 15000).toLocaleString("fr-FR")} F CFA`
      : "";
  };
  intentionSelect.addEventListener("change", update);
  quantityInput.addEventListener("input", update);
  update();
}

if (registrationForm && registrationStatus) {
  const groupType = document.getElementById("group-type");
  const attendance = document.getElementById("attendance");
  const companionsInput = document.getElementById("companions-count");
  configureCompanions(
    groupType,
    attendance,
    document.getElementById("companions-wrapper"),
    companionsInput,
  );
  const intention = document.getElementById("intention");
  const cardiganQuantity = document.getElementById("cardigan-quantity");
  configurePurchaseQuantity(
    intention,
    document.getElementById("purchase-box"),
    cardiganQuantity,
    document.getElementById("purchase-total"),
  );
  registrationForm.addEventListener("submit", (event) => {
    event.preventDefault();
    showStatus(registrationStatus, "");
    try {
      const registrations = readRegistrations();
      const values = formValues(registrationForm);
      const registration = {
        ...values,
        age: values.age ? Number(values.age) : "",
        companionsCount: values.groupType === "accompanied" && values.attendance === "present"
          ? Number(values.companionsCount || 1)
          : 0,
        cardiganQuantity: values.intention === "buy" ? Number(values.cardiganQuantity || 1) : 0,
        createdAt: new Date().toISOString(),
        reference: createReference(registrations),
      };
      saveRegistrations([...registrations, registration]);
      window.location.href = `billet.html?reference=${encodeURIComponent(registration.reference)}&new=1`;
    } catch (error) {
      showStatus(
        registrationStatus,
        error instanceof DOMException && error.name === "QuotaExceededError"
          ? "Le navigateur n'a plus assez d'espace pour enregistrer l'inscription."
          : `Impossible d'enregistrer l'inscription : ${error.message}`,
        true,
      );
    }
  });
}

const rsvpForm = document.getElementById("rsvp-form");
const rsvpStatus = document.getElementById("rsvp-status");

if (rsvpForm && rsvpStatus) {
  const groupType = document.getElementById("rsvp-group-type");
  const attendance = document.getElementById("rsvp-attendance");
  const companionsInput = document.getElementById("rsvp-companions");
  configureCompanions(
    groupType,
    attendance,
    document.getElementById("rsvp-companions-wrapper"),
    companionsInput,
  );
  const prefillRsvp = () => {
    try {
      const reference = document.getElementById("rsvp-reference").value.trim().toLowerCase();
      const lastName = document.getElementById("rsvp-last-name").value.trim().toLocaleLowerCase("fr");
      if (!reference || !lastName) return;
      const registration = readRegistrations().find(
        (item) =>
          item.reference.toLowerCase() === reference &&
          item.lastName.toLocaleLowerCase("fr") === lastName,
      );
      if (!registration) return;
      attendance.value = registration.attendance;
      groupType.value = Number(registration.companionsCount || 0) > 0 ? "accompanied" : "alone";
      companionsInput.value = String(registration.companionsCount || 0);
      attendance.dispatchEvent(new Event("change"));
      groupType.dispatchEvent(new Event("change"));
      showStatus(rsvpStatus, "Inscription retrouvée : vérifiez puis confirmez votre réponse.");
    } catch (error) {
      showStatus(rsvpStatus, `Impossible de retrouver l'inscription : ${error.message}`, true);
    }
  };
  document.getElementById("rsvp-reference").addEventListener("change", prefillRsvp);
  document.getElementById("rsvp-last-name").addEventListener("change", prefillRsvp);
  rsvpForm.addEventListener("submit", (event) => {
    event.preventDefault();
    showStatus(rsvpStatus, "");
    try {
      const values = formValues(rsvpForm);
      const registrations = readRegistrations();
      const registration = registrations.find(
        (item) =>
          item.reference.toLowerCase() === values.reference.trim().toLowerCase() &&
          item.lastName.toLocaleLowerCase("fr") === values.lastName.trim().toLocaleLowerCase("fr"),
      );
      if (!registration) {
        showStatus(
          rsvpStatus,
          "Inscription introuvable sur cet appareil. Vérifiez la référence et le nom utilisés lors de l'inscription.",
          true,
        );
        return;
      }

      registration.attendance = values.attendance;
      registration.groupType = values.groupType;
      registration.companionsCount = values.groupType === "accompanied" && values.attendance === "present"
        ? Number(values.companionsCount || 1)
        : 0;
      registration.updatedAt = new Date().toISOString();
      saveRegistrations(registrations);
      window.location.href = `billet.html?reference=${encodeURIComponent(registration.reference)}&updated=1`;
    } catch (error) {
      showStatus(rsvpStatus, `Impossible de mettre à jour votre réponse : ${error.message}`, true);
    }
  });
}

const ticketLookupForm = document.getElementById("ticket-lookup-form");
const ticketCard = document.getElementById("ticket-card");
const ticketStatus = document.getElementById("ticket-status");
let displayedRegistration = null;

function displayTicket(reference) {
  const registration = readRegistrations().find(
    (item) => item.reference.toLowerCase() === reference.trim().toLowerCase(),
  );
  if (!registration) {
    ticketCard.classList.add("hidden");
    showStatus(
      ticketStatus,
      "Aucune inscription avec cette référence n'a été trouvée dans ce navigateur.",
      true,
    );
    return false;
  }

  displayedRegistration = registration;
  document.getElementById("ticket-name").textContent =
    `${registration.firstName} ${registration.lastName}`;
  document.getElementById("ticket-reference-value").textContent = registration.reference;
  document.getElementById("ticket-attendance").textContent =
    attendanceLabels[registration.attendance] || "Réponse non renseignée";
  document.getElementById("ticket-companions").textContent =
    registration.attendance === "present"
      ? registration.companionsCount > 0
        ? `Accompagné(e) de ${registration.companionsCount} personne(s)`
        : "Seul(e)"
      : "—";
  document.getElementById("ticket-city").textContent = registration.city || "—";
  document.getElementById("ticket-size").textContent = registration.desiredSize || "Non renseignée";
  document.getElementById("ticket-beverages").textContent =
    beverageLabels[registration.beverages] || "Non renseignée";
  document.getElementById("ticket-intention").textContent =
    intentionLabels[registration.intention] || "Non renseigné";
  document.getElementById("ticket-quantity").textContent =
    registration.intention === "buy"
      ? `${registration.cardiganQuantity || 1} × 15 000 F CFA`
      : "Pas d'achat prévu pour le moment";
  document.getElementById("qr-reference-label").textContent = registration.reference;
  const qrContainer = document.getElementById("ticket-qr");
  qrContainer.replaceChildren();
  if (typeof QRCode === "undefined") {
    throw new Error("Le générateur du code QR n'a pas pu être chargé.");
  }
  new QRCode(qrContainer, {
    text: `INVASION CARDIGAN 2026|REFERENCE:${registration.reference}`,
    width: 240,
    height: 240,
    colorDark: "#101010",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.H,
  });
  ticketCard.classList.remove("hidden");
  showStatus(
    ticketStatus,
    new URLSearchParams(window.location.search).has("new")
      ? "Votre inscription est enregistrée. Conservez cette référence."
      : new URLSearchParams(window.location.search).has("updated")
        ? "Votre réponse a été mise à jour."
        : "",
  );
  ticketCard.scrollIntoView({ behavior: "smooth", block: "center" });
  return true;
}

if (ticketLookupForm && ticketCard && ticketStatus) {
  ticketLookupForm.addEventListener("submit", (event) => {
    event.preventDefault();
    try {
      displayTicket(formValues(ticketLookupForm).reference);
    } catch (error) {
      showStatus(ticketStatus, `Impossible de retrouver l'inscription : ${error.message}`, true);
    }
  });

  const reference = new URLSearchParams(window.location.search).get("reference");
  if (reference) {
    document.getElementById("ticket-reference").value = reference;
    try {
      displayTicket(reference);
    } catch (error) {
      showStatus(ticketStatus, `Impossible de retrouver l'inscription : ${error.message}`, true);
    }
  }
}

function ticketText(registration) {
  return [
    "INVASION DE CARDIGAN — ÉVÉNEMENT DE LANCEMENT",
    "14 novembre 2026",
    "",
    `Participant(e) : ${registration.firstName} ${registration.lastName}`,
    `Référence : ${registration.reference}`,
    `Présence : ${attendanceLabels[registration.attendance] || "Non renseignée"}`,
    `Groupe : ${registration.companionsCount > 0 ? `Accompagné(e) de ${registration.companionsCount} personne(s)` : "Seul(e)"}`,
    `Boissons : ${beverageLabels[registration.beverages] || "Non renseignée"}`,
    `Taille : ${registration.desiredSize || "Non renseignée"}`,
    `Objectif : ${intentionLabels[registration.intention] || "Non renseigné"}`,
    "Prix unitaire cardigan : 15 000 F CFA",
    `Quantité envisagée : ${registration.intention === "buy" ? registration.cardiganQuantity || 1 : 0}`,
    "",
    "Récapitulatif enregistré localement dans votre navigateur.",
  ].join("\n");
}

document.getElementById("download-ticket")?.addEventListener("click", () => {
  try {
    if (!displayedRegistration) throw new Error("Aucune inscription n'est affichée.");
    const file = new Blob([ticketText(displayedRegistration)], { type: "text/plain;charset=utf-8" });
    const url = URL.createObjectURL(file);
    const link = document.createElement("a");
    link.href = url;
    link.download = `invasion-${displayedRegistration.reference.toLowerCase()}.txt`;
    link.click();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch (error) {
    showStatus(ticketStatus, `Impossible de créer le récapitulatif : ${error.message}`, true);
  }
});

document.getElementById("download-qr")?.addEventListener("click", () => {
  const canvas = document.querySelector("#ticket-qr canvas");
  const logo = document.querySelector(".qr-center-logo");
  if (!canvas || !logo || !displayedRegistration) {
    showStatus(ticketStatus, "Le code QR n'est pas encore disponible.", true);
    return;
  }
  const image = new Image();
  image.onload = () => {
    const output = document.createElement("canvas");
    output.width = canvas.width;
    output.height = canvas.height;
    const context = output.getContext("2d");
    context.drawImage(canvas, 0, 0);
    const logoSize = output.width * 0.19;
    const padding = output.width * 0.025;
    const center = output.width / 2;
    context.fillStyle = "#fff";
    context.fillRect(
      center - logoSize / 2 - padding,
      center - logoSize / 2 - padding,
      logoSize + padding * 2,
      logoSize + padding * 2,
    );
    context.drawImage(image, center - logoSize / 2, center - logoSize / 2, logoSize, logoSize);
    const link = document.createElement("a");
    link.href = output.toDataURL("image/png");
    link.download = `invasion-${displayedRegistration.reference.toLowerCase()}-qr.png`;
    link.click();
  };
  image.onerror = () => showStatus(ticketStatus, "Impossible d'ajouter le logo au code QR.", true);
  image.src = logo.src;
});

document.getElementById("print-ticket")?.addEventListener("click", () => window.print());

const adminTable = document.getElementById("admin-registrations");
const adminStatus = document.getElementById("admin-status");
const adminLoginForm = document.getElementById("admin-login-form");
const adminLoginPanel = document.getElementById("admin-login-panel");
const adminContent = document.getElementById("admin-content");
const adminSessionKey = "invasion-admin-unlocked";

if (adminLoginForm && adminLoginPanel && adminContent) {
  const isUnlocked = window.sessionStorage.getItem(adminSessionKey) === "1";
  adminLoginPanel.classList.toggle("hidden", isUnlocked);
  adminContent.hidden = !isUnlocked;
  document.body.classList.toggle("admin-locked", !isUnlocked);

  adminLoginForm.addEventListener("submit", (event) => {
    event.preventDefault();
    const status = document.getElementById("admin-login-status");
    const enteredCode = formValues(adminLoginForm).accessCode;
    if (enteredCode !== "Invasion4249") {
      showStatus(status, "Code incorrect. Veuillez réessayer.", true);
      adminLoginForm.reset();
      document.getElementById("admin-access-code").focus();
      return;
    }

    window.sessionStorage.setItem(adminSessionKey, "1");
    window.location.reload();
  });

  document.getElementById("admin-lock").addEventListener("click", () => {
    window.sessionStorage.removeItem(adminSessionKey);
    window.location.reload();
  });
}

function registrationGroup(registration) {
  return Number(registration.companionsCount || 0) > 0 ? "Accompagné(e)" : "Seul(e)";
}

function adminCell(row, value) {
  const cell = document.createElement("td");
  cell.textContent = value || "—";
  row.append(cell);
  return cell;
}

function renderAdminList() {
  if (!adminTable) return;
  const registrations = readRegistrations();
  const search = document.getElementById("admin-search").value.trim().toLocaleLowerCase("fr");
  const filter = document.getElementById("admin-filter").value;
  const presentCount = registrations.filter((item) => item.attendance === "present").length;
  const expectedPeople = registrations.reduce(
    (total, item) => total + (item.attendance === "present" ? 1 + Number(item.companionsCount || 0) : 0),
    0,
  );
  document.getElementById("admin-total").textContent = String(registrations.length);
  document.getElementById("admin-present").textContent = String(presentCount);
  document.getElementById("admin-people").textContent = String(expectedPeople);

  const visible = registrations.filter((item) => {
    const searchable = `${item.firstName} ${item.lastName} ${item.city} ${item.reference}`.toLocaleLowerCase("fr");
    return (!search || searchable.includes(search)) && (!filter || item.attendance === filter);
  });
  adminTable.replaceChildren();
  for (const registration of visible) {
    const row = document.createElement("tr");
    adminCell(row, `${registration.reference} — ${registration.firstName} ${registration.lastName}`);
    adminCell(row, registration.city);
    adminCell(row, attendanceLabels[registration.attendance] || "Non renseignée");
    adminCell(
      row,
      registration.attendance === "present" && Number(registration.companionsCount || 0) > 0
        ? `${registrationGroup(registration)} · ${registration.companionsCount} personne(s)`
        : registration.attendance === "present"
          ? "Seul(e)"
          : "—",
    );
    adminCell(row, beverageLabels[registration.beverages] || "Non renseignée");
    adminCell(row, knowledgeLabels[registration.cardiganKnowledge] || "Non renseignée");
    adminCell(row, intentionLabels[registration.intention] || "Non renseigné");
    const actions = document.createElement("td");
    const edit = document.createElement("button");
    edit.type = "button";
    edit.className = "btn btn-secondary btn-sm";
    edit.dataset.action = "edit";
    edit.dataset.reference = registration.reference;
    edit.textContent = "Modifier";
    const remove = document.createElement("button");
    remove.type = "button";
    remove.className = "btn btn-danger btn-sm";
    remove.dataset.action = "delete";
    remove.dataset.reference = registration.reference;
    remove.textContent = "Supprimer";
    actions.append(edit, remove);
    row.append(actions);
    adminTable.append(row);
  }
  document.getElementById("admin-visible-count").textContent =
    `${visible.length} résultat${visible.length === 1 ? "" : "s"}`;
  document.getElementById("admin-empty").classList.toggle("hidden", visible.length !== 0);
}

function renderAdminListSafely() {
  try {
    renderAdminList();
  } catch (error) {
    showStatus(adminStatus, `Impossible de lire les inscriptions : ${error.message}`, true);
  }
}

if (adminTable && adminStatus && window.sessionStorage.getItem(adminSessionKey) === "1") {
  const editPanel = document.getElementById("admin-edit-panel");
  const editForm = document.getElementById("admin-edit-form");
  const editGroupType = document.getElementById("edit-group-type");
  const editAttendance = document.getElementById("edit-attendance");
  const editCompanions = document.getElementById("edit-companions");
  const updateEditCompanions = () => {
    const enabled = editGroupType.value === "accompanied" && editAttendance.value === "present";
    editCompanions.disabled = !enabled;
    editCompanions.required = enabled;
    if (enabled && Number(editCompanions.value) < 1) editCompanions.value = "1";
    if (!enabled) editCompanions.value = "0";
  };

  document.getElementById("admin-search").addEventListener("input", renderAdminListSafely);
  document.getElementById("admin-filter").addEventListener("change", renderAdminListSafely);
  editGroupType.addEventListener("change", updateEditCompanions);
  editAttendance.addEventListener("change", updateEditCompanions);

  adminTable.addEventListener("click", (event) => {
    try {
      const button = event.target.closest("button[data-action]");
      if (!button) return;
      const registrations = readRegistrations();
      const index = registrations.findIndex((item) => item.reference === button.dataset.reference);
      if (index < 0) {
        showStatus(adminStatus, "Cette inscription n'existe plus.", true);
        renderAdminListSafely();
        return;
      }

      if (button.dataset.action === "delete") {
        if (!window.confirm(`Supprimer l'inscription de ${registrations[index].firstName} ${registrations[index].lastName} ?`)) return;
        registrations.splice(index, 1);
        saveRegistrations(registrations);
        showStatus(adminStatus, "Inscription supprimée de ce navigateur.");
        renderAdminListSafely();
        return;
      }

      const registration = registrations[index];
      for (const field of editForm.elements) {
        if (!field.name) continue;
        if (field.name === "groupType") {
          field.value = registrationGroup(registration) === "Accompagné(e)" ? "accompanied" : "alone";
        } else if (field.name === "companionsCount") {
          field.value = String(registration.companionsCount || 0);
        } else if (field.name === "beverages") {
          field.value = registration.beverages === "sweet" ? "non_alcoholic" : registration.beverages || "none";
        } else if (field.name === "cardiganQuantity") {
          field.value = String(registration.cardiganQuantity || 0);
        } else {
          field.value = registration[field.name] ?? "";
        }
      }
      if (editForm.elements.groupType.value === "accompanied" && editForm.elements.attendance.value === "present") {
        editForm.elements.companionsCount.value = String(Math.max(1, Number(editForm.elements.companionsCount.value)));
      }
      updateEditCompanions();
      editPanel.classList.remove("hidden");
      editPanel.scrollIntoView({ behavior: "smooth", block: "start" });
    } catch (error) {
      showStatus(adminStatus, `Impossible d'effectuer cette action : ${error.message}`, true);
    }
  });

  editForm.addEventListener("submit", (event) => {
    event.preventDefault();
    try {
      const values = formValues(editForm);
      const registrations = readRegistrations();
      const registration = registrations.find((item) => item.reference === values.reference);
      if (!registration) throw new Error("Inscription introuvable.");
      Object.assign(registration, values, {
        age: values.age ? Number(values.age) : "",
        cardiganQuantity: values.intention === "buy" ? Number(values.cardiganQuantity || 1) : 0,
        groupType: values.groupType,
        companionsCount: values.attendance === "present" && values.groupType === "accompanied"
          ? Number(values.companionsCount || 1)
          : 0,
        updatedAt: new Date().toISOString(),
      });
      saveRegistrations(registrations);
      editPanel.classList.add("hidden");
      showStatus(adminStatus, "Modifications enregistrées.");
      renderAdminList();
    } catch (error) {
      showStatus(adminStatus, `Impossible d'enregistrer : ${error.message}`, true);
    }
  });

  document.getElementById("admin-cancel-edit").addEventListener("click", () => {
    editPanel.classList.add("hidden");
  });
  document.getElementById("admin-export").addEventListener("click", () => {
    try {
      const registrations = readRegistrations();
      const columns = [
        ["reference", "Référence"], ["firstName", "Prénom"], ["lastName", "Nom"],
        ["city", "Ville"], ["age", "Âge"], ["attendance", "Présence"],
        ["groupType", "Groupe"], ["companionsCount", "Accompagnants"],
        ["beverages", "Boissons"],
        ["cardiganKnowledge", "Connaissance du cardigan"], ["intention", "Objectif"],
        ["cardiganQuantity", "Quantité envisagée"],
        ["desiredSize", "Taille"], ["preferredColor", "Couleur ou modèle"],
        ["comment", "Commentaire"], ["createdAt", "Inscrit le"],
      ];
      const csvValue = (value) => {
        const text = String(value ?? "");
        const safeText = /^[=+\-@]/.test(text) ? `'${text}` : text;
        return `"${safeText.replaceAll('"', '""')}"`;
      };
      const exportValue = (registration, key) => {
        if (key === "attendance") return attendanceLabels[registration.attendance] || registration.attendance;
        if (key === "groupType") {
          return registration.groupType === "accompanied" || Number(registration.companionsCount || 0) > 0
            ? "Accompagné(e)"
            : "Seul(e)";
        }
        if (key === "beverages") return beverageLabels[registration.beverages] || registration.beverages;
        if (key === "cardiganKnowledge") {
          return knowledgeLabels[registration.cardiganKnowledge] || registration.cardiganKnowledge;
        }
        if (key === "intention") return intentionLabels[registration.intention] || registration.intention;
        return registration[key];
      };
      const csv = [
        columns.map(([, title]) => csvValue(title)).join(";"),
        ...registrations.map((item) => columns.map(([key]) => csvValue(exportValue(item, key))).join(";")),
      ].join("\r\n");
      const file = new Blob(["\uFEFF", csv], { type: "text/csv;charset=utf-8" });
      const url = URL.createObjectURL(file);
      const link = document.createElement("a");
      link.href = url;
      link.download = "invasion-inscriptions.csv";
      link.click();
      window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (error) {
      showStatus(adminStatus, `Impossible d'exporter la liste : ${error.message}`, true);
    }
  });
  document.getElementById("admin-print").addEventListener("click", () => window.print());
  renderAdminListSafely();
}
