from datetime import date
import hmac
from io import BytesIO

from flask import Blueprint, current_app, flash, redirect, render_template, request, send_file, session, url_for
from sqlalchemy import desc
import segno
from PIL import Image, ImageDraw, ImageFont

from app import db
from app.models import EventRegistration

bp = Blueprint("main", __name__)

PRESENCE_FILTERS = {
    "present": "Confirmés",
    "unsure": "Hésitants",
    "absent": "Absents",
}


def _require_admin():
    if not session.get("admin_authenticated"):
        flash("Veuillez vous connecter pour accéder à l’espace administrateur.", "error")
        return redirect(url_for("main.admin_login"))
    return None


def _coerce_int(value, field_label):
    if value in (None, ""):
        return None
    try:
        parsed = int(value)
    except (TypeError, ValueError) as exc:
        raise ValueError(f"Le champ « {field_label} » doit être un nombre entier.") from exc
    return parsed


def _safe_strip(value):
    if value is None:
        return ""
    return str(value).strip()


def _next_reference():
    prefix = f"CARD-{date.today().year}-"
    references = (
        EventRegistration.query.filter(EventRegistration.reference.like(f"{prefix}%"))
        .with_entities(EventRegistration.reference)
        .all()
    )
    numbers = []
    for (reference,) in references:
        try:
            number = int(reference[len(prefix):])
        except ValueError:
            continue
        if number > 0:
            numbers.append(number)
    return f"{prefix}{max(numbers, default=0) + 1:04d}"


def _build_registration_from_form():
    attendance = _safe_strip(request.form.get("attendance"))
    if attendance not in EventRegistration.PRESENCE_CHOICES:
        raise ValueError("Merci de sélectionner votre réponse concernant votre présence.")

    companions_count = 0
    if attendance == "present":
        companions_count = _coerce_int(
            request.form.get("companions_count"), "Nombre de personnes qui vous accompagnent"
        )
        if companions_count is None:
            raise ValueError("Merci de préciser combien de personnes vous accompagneront.")
        if companions_count < 0:
            raise ValueError("Le nombre de personnes qui vous accompagnent ne peut pas être négatif.")

    beverages = _safe_strip(request.form.get("beverages"))
    if beverages not in EventRegistration.BEVERAGE_CHOICES:
        raise ValueError("Merci de choisir une préférence de boissons.")

    cardigan_knowledge = _safe_strip(request.form.get("cardigan_knowledge"))
    if cardigan_knowledge not in EventRegistration.KNOWLEDGE_CHOICES:
        raise ValueError("Merci d'indiquer votre connaissance du cardigan.")

    intention = _safe_strip(request.form.get("intention"))
    if intention not in EventRegistration.INTENTION_CHOICES:
        raise ValueError("Merci de sélectionner votre objectif principal.")

    first_name = _safe_strip(request.form.get("first_name"))
    last_name = _safe_strip(request.form.get("last_name"))
    city = _safe_strip(request.form.get("city"))
    age = _coerce_int(request.form.get("age"), "Âge")
    if age is not None and age < 0:
        raise ValueError("L'âge ne peut pas être négatif.")

    if not all((first_name, last_name, city)):
        raise ValueError("Veuillez remplir tous les champs obligatoires.")

    return EventRegistration(
        reference=_next_reference(),
        first_name=first_name,
        last_name=last_name,
        age=age,
        city=city,
        attendance=attendance,
        companions_count=companions_count,
        beverages=beverages,
        cardigan_knowledge=cardigan_knowledge,
        intention=intention,
        desired_size=_safe_strip(request.form.get("desired_size")) or None,
        preferred_color=_safe_strip(request.form.get("preferred_color")) or None,
        budget=_safe_strip(request.form.get("budget")) or None,
        comment=_safe_strip(request.form.get("comment")) or None,
    )


def _stats_payload():
    registrations = EventRegistration.query.all()
    total = len(registrations)
    present = EventRegistration.query.filter_by(attendance="present").count()
    unsure = EventRegistration.query.filter_by(attendance="unsure").count()
    absent = EventRegistration.query.filter_by(attendance="absent").count()
    expected_attendees = sum(reg.expected_attendance_count for reg in registrations)
    potential_buyers = EventRegistration.query.filter_by(intention="buy").count()
    discoverers = EventRegistration.query.filter_by(intention="discover").count()
    beverage_counts = {
        key: EventRegistration.query.filter_by(beverages=key).count()
        for key in ["alcohol", "sweet", "both", "none"]
    }
    city_counts = {}
    for city in EventRegistration.query.with_entities(EventRegistration.city).distinct():
        name = city[0]
        if name:
            city_counts[name] = EventRegistration.query.filter_by(city=name).count()
    knowledge_counts = {
        key: EventRegistration.query.filter_by(cardigan_knowledge=key).count()
        for key in ["known", "heard", "new"]
    }
    return {
        "total": total,
        "present": present,
        "unsure": unsure,
        "absent": absent,
        "expected_attendees": expected_attendees,
        "potential_buyers": potential_buyers,
        "discoverers": discoverers,
        "beverage_counts": beverage_counts,
        "city_counts": city_counts,
        "knowledge_counts": knowledge_counts,
    }


@bp.route("/")
def index():
    return render_template("index.html", stats=_stats_payload())


@bp.route("/inscriptions")
def list_registrations():
    query = EventRegistration.query
    attendance_filter = request.args.get("presence", "")
    city_filter = request.args.get("ville", "").strip()
    search = request.args.get("q", "").strip()
    intention_filter = request.args.get("intention", "")
    beverage_filter = request.args.get("boisson", "")

    if attendance_filter in PRESENCE_FILTERS:
        query = query.filter_by(attendance=attendance_filter)
    if city_filter:
        query = query.filter(EventRegistration.city.ilike(f"%{city_filter}%"))
    if search:
        query = query.filter(
            EventRegistration.first_name.ilike(f"%{search}%")
            | EventRegistration.last_name.ilike(f"%{search}%")
        )
    if intention_filter in EventRegistration.INTENTION_CHOICES:
        query = query.filter_by(intention=intention_filter)
    if beverage_filter in EventRegistration.BEVERAGE_CHOICES:
        query = query.filter_by(beverages=beverage_filter)

    registrations = query.order_by(desc(EventRegistration.created_at)).all()
    return render_template(
        "list.html",
        registrations=registrations,
        presence_choices=PRESENCE_FILTERS,
        city_filter=city_filter,
        intention_filter=intention_filter,
        beverage_filter=beverage_filter,
        search=search,
        current_presence=attendance_filter,
        intention_choices=EventRegistration.INTENTION_CHOICES,
        beverage_choices=EventRegistration.BEVERAGE_CHOICES,
    )


@bp.route("/inscriptions/nouvelle", methods=["GET", "POST"])
def new_registration():
    if request.method == "POST":
        try:
            registration = _build_registration_from_form()
        except ValueError as err:
            flash(str(err), "error")
        else:
            db.session.add(registration)
            db.session.commit()
            flash(
                f"Merci ! Votre inscription a bien été enregistrée. Référence : {registration.reference}",
                "success",
            )
            return redirect(url_for("main.registration_detail", reference=registration.reference))

    return render_template(
        "form.html",
        attendance_choices=EventRegistration.PRESENCE_CHOICES,
        beverage_choices=EventRegistration.BEVERAGE_CHOICES,
        knowledge_choices=EventRegistration.KNOWLEDGE_CHOICES,
        intention_choices=EventRegistration.INTENTION_CHOICES,
    )


@bp.route("/inscriptions/<reference>")
def registration_detail(reference):
    registration = EventRegistration.query.filter_by(reference=reference).first_or_404()
    return render_template("detail.html", registration=registration)


@bp.route("/inscriptions/<reference>/ticket.png")
def registration_ticket(reference):
    registration = EventRegistration.query.filter_by(reference=reference).first_or_404()

    ticket = Image.new("RGB", (900, 1120), "#050505")
    draw = ImageDraw.Draw(ticket)
    font_large = ImageFont.load_default(size=42)
    font_medium = ImageFont.load_default(size=30)
    font_small = ImageFont.load_default(size=23)

    def draw_centered(text, y, font, fill="#ffffff"):
        bounds = draw.textbbox((0, 0), text, font=font)
        draw.text(((ticket.width - (bounds[2] - bounds[0])) / 2, y), text, font=font, fill=fill)

    draw.rounded_rectangle((24, 24, 876, 1096), radius=28, outline="#f5f5f5", width=3)

    logo_source = Image.open(
        BytesIO(current_app.open_resource("static/invasion-logo.png").read())
    ).convert("RGBA")
    alpha = logo_source.getchannel("A").point(lambda value: 255 if value > 32 else 0)
    logo_source = logo_source.crop(alpha.getbbox())
    logo = logo_source.copy()
    logo.thumbnail((490, 170), Image.Resampling.LANCZOS)
    ticket.paste(logo, ((ticket.width - logo.width) // 2, 62), logo)

    draw_centered("BILLET D'ACCÈS", 265, font_large)
    draw_centered("INVITATION OFFICIELLE", 330, font_small, fill="#c6c6c6")
    draw.line((90, 385, 810, 385), fill="#555555", width=2)
    draw_centered(registration.full_name, 415, font_medium)
    draw_centered(registration.reference, 465, font_small, fill="#d0d0d0")
    draw_centered(current_app.config["EVENT_DATE"], 510, font_small, fill="#c6c6c6")
    draw_centered(registration.presence_label, 550, font_small)

    qr_payload = "\n".join(
        (
            current_app.config["BRAND_NAME"],
            f"Référence : {registration.reference}",
            f"Nom : {registration.full_name}",
            f"Événement : {current_app.config['EVENT_DATE']}",
        )
    )
    qr = segno.make(
        qr_payload,
        error="H",
        encoding="utf-8",
        eci=True,
        boost_error=False,
        micro=False,
    )
    qr_matrix = qr.matrix
    quiet_zone = 4
    module_count = len(qr_matrix)
    module_scale = max(1, 420 // (module_count + quiet_zone * 2))
    qr_size = (module_count + quiet_zone * 2) * module_scale
    qr_image = Image.new("RGB", (qr_size, qr_size), "white")
    qr_draw = ImageDraw.Draw(qr_image)
    for row_index, row in enumerate(qr_matrix):
        for column_index, is_dark in enumerate(row):
            if is_dark:
                left = (column_index + quiet_zone) * module_scale
                top = (row_index + quiet_zone) * module_scale
                qr_draw.rectangle(
                    (left, top, left + module_scale - 1, top + module_scale - 1),
                    fill="black",
                )

    logo_card = Image.new("RGB", (150, 78), "white")
    logo_mark = logo_source.copy()
    logo_mark.thumbnail((126, 54), Image.Resampling.LANCZOS)
    dark_logo = Image.new("RGBA", logo_mark.size, "#050505")
    dark_logo.putalpha(logo_mark.getchannel("A"))
    logo_card.paste(
        dark_logo,
        ((logo_card.width - dark_logo.width) // 2, (logo_card.height - dark_logo.height) // 2),
        dark_logo,
    )
    qr_draw = ImageDraw.Draw(qr_image)
    card_x = (qr_size - logo_card.width) // 2
    card_y = (qr_size - logo_card.height) // 2
    qr_draw.rounded_rectangle(
        (card_x - 8, card_y - 8, card_x + logo_card.width + 8, card_y + logo_card.height + 8),
        radius=12,
        fill="white",
    )
    qr_image.paste(logo_card, (card_x, card_y))
    ticket.paste(qr_image, ((ticket.width - qr_size) // 2, 600))

    draw_centered("PRESENTEZ CE QR CODE A L'ENTREE", 1050, font_small, fill="#c6c6c6")

    output = BytesIO()
    ticket.save(output, format="PNG")
    output.seek(0)
    return send_file(
        output,
        mimetype="image/png",
        as_attachment=request.args.get("download") == "1",
        download_name=f"ticket-{registration.reference}.png",
    )


@bp.route("/participation", methods=["GET", "POST"])
def participation():
    if request.method == "POST":
        reference = _safe_strip(request.form.get("reference")).casefold()
        last_name = _safe_strip(request.form.get("last_name")).casefold()
        registration = EventRegistration.query.filter(
            db.func.lower(EventRegistration.reference) == reference
        ).first()

        if (
            not registration
            or not last_name
            or registration.last_name.strip().casefold() != last_name
        ):
            flash("Référence ou nom invalide. Vérifiez vos informations.", "error")
            return render_template("participation.html")

        attendance = _safe_strip(request.form.get("attendance"))
        if attendance not in EventRegistration.PRESENCE_CHOICES:
            flash("Merci de sélectionner votre réponse concernant votre présence.", "error")
            return render_template("participation.html")

        if attendance == "present":
            try:
                companions_count = _coerce_int(
                    request.form.get("companions_count"), "Nombre d'accompagnants"
                )
            except ValueError as err:
                flash(str(err), "error")
                return render_template("participation.html")
            if companions_count is None or companions_count < 0:
                flash("Le nombre d'accompagnants doit être supérieur ou égal à zéro.", "error")
                return render_template("participation.html")
        else:
            companions_count = 0

        registration.attendance = attendance
        registration.companions_count = companions_count
        db.session.commit()
        flash("Votre réponse a bien été enregistrée. Merci !", "success")
        return redirect(url_for("main.participation"))

    return render_template("participation.html")


@bp.route("/admin")
def admin_dashboard():
    guard = _require_admin()
    if guard is not None:
        return guard
    stats = _stats_payload()
    registrations = EventRegistration.query.order_by(desc(EventRegistration.created_at)).all()
    return render_template("admin.html", stats=stats, registrations=registrations)


@bp.route("/admin/export.csv")
def export_csv():
    guard = _require_admin()
    if guard is not None:
        return guard

    import csv
    import io

    rows = EventRegistration.query.order_by(desc(EventRegistration.created_at)).all()
    output = io.StringIO()
    writer = csv.writer(output)
    writer.writerow([
        "reference",
        "nom",
        "prenom",
        "age",
        "ville",
        "presence",
        "accompagnants",
        "boissons",
        "connaissance",
        "objectif",
        "taille",
        "couleur",
        "budget",
        "commentaire",
        "created_at",
    ])
    for row in rows:
        writer.writerow([
            row.reference,
            row.last_name,
            row.first_name,
            row.age or "",
            row.city or "",
            row.presence_label,
            row.companions_count or 0,
            row.beverage_label,
            row.knowledge_label,
            row.intention_label,
            row.desired_size or "",
            row.preferred_color or "",
            row.budget or "",
            row.comment or "",
            row.created_at.isoformat() if row.created_at else "",
        ])
    output.seek(0)
    return send_file(
        io.BytesIO(output.getvalue().encode("utf-8-sig")),
        mimetype="text/csv",
        as_attachment=True,
        download_name="inscriptions-cardigan.csv",
    )


@bp.route("/admin/<reference>", methods=["GET", "POST"])
def admin_registration(reference):
    guard = _require_admin()
    if guard is not None:
        return guard

    registration = EventRegistration.query.filter_by(reference=reference).first_or_404()
    if request.method == "POST":
        companions_count = _coerce_int(
            request.form.get("companions_count"), "Nombre de personnes qui accompagnent le participant"
        )
        if companions_count is None or companions_count < 0:
            flash("Le nombre d'accompagnants doit être un nombre positif ou égal à zéro.", "error")
            return render_template("admin_detail.html", registration=registration)

        registration.first_name = _safe_strip(request.form.get("first_name")) or registration.first_name
        registration.last_name = _safe_strip(request.form.get("last_name")) or registration.last_name
        registration.age = _coerce_int(request.form.get("age"), "Âge") or registration.age
        registration.city = _safe_strip(request.form.get("city")) or registration.city
        registration.attendance = request.form.get("attendance") if request.form.get("attendance") in {"present", "absent", "unsure"} else registration.attendance
        registration.companions_count = companions_count
        registration.beverages = request.form.get("beverages") if request.form.get("beverages") in {"alcohol", "sweet", "both", "none"} else registration.beverages
        registration.cardigan_knowledge = request.form.get("cardigan_knowledge") if request.form.get("cardigan_knowledge") in {"known", "heard", "new"} else registration.cardigan_knowledge
        registration.intention = request.form.get("intention") if request.form.get("intention") in {"buy", "discover", "inform", "accompany"} else registration.intention
        registration.desired_size = _safe_strip(request.form.get("desired_size")) or registration.desired_size
        registration.preferred_color = _safe_strip(request.form.get("preferred_color")) or registration.preferred_color
        registration.budget = _safe_strip(request.form.get("budget")) or registration.budget
        registration.comment = _safe_strip(request.form.get("comment")) or registration.comment
        db.session.commit()
        flash("Inscription mise à jour.", "success")
        return redirect(url_for("main.admin_registration", reference=reference))
    return render_template("admin_detail.html", registration=registration)


@bp.route("/admin/<reference>/delete", methods=["POST"])
def delete_registration(reference):
    guard = _require_admin()
    if guard is not None:
        return guard

    registration = EventRegistration.query.filter_by(reference=reference).first_or_404()
    db.session.delete(registration)
    db.session.commit()
    flash("L'inscription a bien été supprimée.", "success")
    return redirect(url_for("main.admin_dashboard"))


@bp.route("/admin/login", methods=["GET", "POST"])
def admin_login():
    admin_password = current_app.config["ADMIN_PASSWORD"]
    if request.method == "POST":
        password = request.form.get("password", "")
        if admin_password and hmac.compare_digest(password, admin_password):
            session.clear()
            session["admin_authenticated"] = True
            flash("Connexion réussie.", "success")
            return redirect(url_for("main.admin_dashboard"))
        if admin_password:
            flash("Mot de passe incorrect.", "error")
    return render_template("admin_login.html", admin_configured=bool(admin_password))


@bp.route("/admin/logout")
def admin_logout():
    session.pop("admin_authenticated", None)
    flash("Déconnexion réussie.", "success")
    return redirect(url_for("main.admin_login"))


@bp.route("/demandes", defaults={"path": ""})
def legacy_requests(path):
    return redirect(url_for("main.list_registrations"))


@bp.route("/demandes/nouvelle", methods=["GET", "POST"])
def legacy_new_request():
    return new_registration()


@bp.route("/demandes/<reference>")
def legacy_detail(reference):
    return redirect(url_for("main.registration_detail", reference=reference))
