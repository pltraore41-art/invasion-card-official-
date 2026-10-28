from datetime import datetime, timezone

from app import db


def _utcnow():
    return datetime.now(timezone.utc)


class EventRegistration(db.Model):
    __tablename__ = "event_registrations"

    id = db.Column(db.Integer, primary_key=True)
    reference = db.Column(db.String(30), unique=True, nullable=False, index=True)

    last_name = db.Column(db.String(120), nullable=False)
    first_name = db.Column(db.String(120), nullable=False)
    age = db.Column(db.Integer)
    city = db.Column(db.String(120))

    attendance = db.Column(db.String(40), nullable=False)
    companions_count = db.Column(db.Integer, default=0)
    beverages = db.Column(db.String(40), nullable=False)
    cardigan_knowledge = db.Column(db.String(50), nullable=False)
    intention = db.Column(db.String(60), nullable=False)
    desired_size = db.Column(db.String(30))
    preferred_color = db.Column(db.String(120))
    budget = db.Column(db.String(50))
    comment = db.Column(db.Text)

    created_at = db.Column(db.DateTime, nullable=False, default=_utcnow)
    updated_at = db.Column(
        db.DateTime, nullable=False, default=_utcnow, onupdate=_utcnow
    )

    STATUS_LABELS = {
        "confirmes": "Confirmés",
        "hesitants": "Hésitants",
        "absents": "Absents",
    }

    PRESENCE_CHOICES = {
        "present": "Oui, je confirme ma présence",
        "absent": "Non, je ne pourrai pas venir",
        "unsure": "Je ne suis pas encore sûr(e)",
    }

    COMPANION_CHOICES = {
        "alone": "Je viens seul(e)",
        "one": "Je viens accompagné(e) d'une personne",
        "many": "Je viens accompagné(e) de plusieurs personnes",
    }

    BEVERAGE_CHOICES = {
        "alcohol": "Boissons alcoolisées",
        "sweet": "Boissons sucrées : jus, sodas, etc.",
        "both": "Les deux",
        "none": "Aucune de ces boissons",
    }

    KNOWLEDGE_CHOICES = {
        "known": "Oui, je le connais déjà",
        "heard": "J'en ai déjà entendu parler",
        "new": "Non, je le découvre",
    }

    INTENTION_CHOICES = {
        "buy": "Je viens avec l'intention d'acheter un cardigan",
        "discover": "Je viens découvrir les modèles avant de décider",
        "inform": "Je viens me renseigner avant un éventuel achat",
        "accompany": "Je viens accompagner un proche",
    }

    @property
    def full_name(self):
        return f"{self.first_name} {self.last_name}".strip()

    @property
    def presence_label(self):
        return self.PRESENCE_CHOICES.get(self.attendance, self.attendance)

    @property
    def companion_label(self):
        if self.attendance != "present":
            return "—"
        if self.companions_count <= 0:
            return self.COMPANION_CHOICES["alone"]
        if self.companions_count == 1:
            return self.COMPANION_CHOICES["one"]
        return self.COMPANION_CHOICES["many"]

    @property
    def beverage_label(self):
        return self.BEVERAGE_CHOICES.get(self.beverages, self.beverages)

    @property
    def knowledge_label(self):
        return self.KNOWLEDGE_CHOICES.get(self.cardigan_knowledge, self.cardigan_knowledge)

    @property
    def intention_label(self):
        return self.INTENTION_CHOICES.get(self.intention, self.intention)

    @property
    def expected_attendance_count(self):
        if self.attendance != "present":
            return 0
        return 1 + int(self.companions_count or 0)

    @property
    def is_potential_buyer(self):
        return self.intention == "buy"

    @property
    def is_discoverer(self):
        return self.intention == "discover"

    @property
    def is_informer(self):
        return self.intention == "inform"
