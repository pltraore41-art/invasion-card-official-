import secrets

from django.core.exceptions import ValidationError
from django.db import models
from django.utils import timezone


class EventRegistration(models.Model):
    class Attendance(models.TextChoices):
        PRESENT = "present", "Oui, je confirme ma présence"
        ABSENT = "absent", "Non, je ne pourrai pas venir"
        UNSURE = "unsure", "Je ne suis pas encore sûr(e)"

    class Beverage(models.TextChoices):
        ALCOHOL = "alcohol", "Boissons alcoolisées"
        SWEET = "sweet", "Boissons sucrées : jus, sodas, etc."
        BOTH = "both", "Les deux"
        NONE = "none", "Aucune de ces boissons"

    class CardiganKnowledge(models.TextChoices):
        KNOWN = "known", "Oui, je le connais déjà"
        HEARD = "heard", "J'en ai déjà entendu parler"
        NEW = "new", "Non, je le découvre"

    class Intention(models.TextChoices):
        BUY = "buy", "Je viens avec l'intention d'acheter un cardigan"
        DISCOVER = "discover", "Je viens découvrir les modèles avant de décider"
        INFORM = "inform", "Je viens me renseigner avant un éventuel achat"
        ACCOMPANY = "accompany", "Je viens accompagner un proche"

    reference = models.CharField(max_length=32, unique=True, editable=False)
    first_name = models.CharField("Prénom", max_length=120)
    last_name = models.CharField("Nom", max_length=120)
    age = models.PositiveSmallIntegerField("Âge", null=True, blank=True)
    city = models.CharField("Ville ou commune", max_length=120)
    attendance = models.CharField("Présence", max_length=16, choices=Attendance.choices)
    companions_count = models.PositiveSmallIntegerField("Nombre d'accompagnants", default=0)
    cardigan_quantity = models.PositiveSmallIntegerField("Quantité de cardigans", default=0)
    beverages = models.CharField("Préférence de boissons", max_length=24, choices=Beverage.choices)
    cardigan_knowledge = models.CharField(
        "Connaissance du cardigan", max_length=16, choices=CardiganKnowledge.choices
    )
    intention = models.CharField("Objectif principal", max_length=16, choices=Intention.choices)
    desired_size = models.CharField("Taille recherchée", max_length=30)
    preferred_color = models.CharField("Couleur ou modèle préféré", max_length=120, blank=True)
    budget = models.CharField("Budget envisagé", max_length=50, blank=True)
    comment = models.TextField("Commentaire ou question", blank=True)
    created_at = models.DateTimeField("Date d'inscription", auto_now_add=True)
    updated_at = models.DateTimeField("Dernière mise à jour", auto_now=True)
    checked_in_at = models.DateTimeField("Date de pointage", null=True, blank=True)

    class Meta:
        ordering = ["-created_at"]
        verbose_name = "inscription"
        verbose_name_plural = "inscriptions"
        indexes = [models.Index(fields=["attendance", "created_at"])]

    def __str__(self) -> str:
        return f"{self.reference} — {self.first_name} {self.last_name}"

    @property
    def full_name(self) -> str:
        return f"{self.first_name} {self.last_name}".strip()

    @property
    def expected_attendees(self) -> int:
        return 1 + self.companions_count if self.attendance == self.Attendance.PRESENT else 0

    def clean(self) -> None:
        super().clean()
        errors: dict[str, str] = {}
        if self.age is not None and self.age > 120:
            errors["age"] = "L'âge doit être compris entre 0 et 120."
        if self.companions_count > 20:
            errors["companions_count"] = "Le nombre d'accompagnants doit être compris entre 0 et 20."
        if self.intention == self.Intention.BUY:
            if not 1 <= self.cardigan_quantity <= 20:
                errors["cardigan_quantity"] = "La quantité doit être comprise entre 1 et 20 cardigans."
        else:
            self.cardigan_quantity = 0
        if len(self.comment) > 1000:
            errors["comment"] = "Le commentaire ne peut pas dépasser 1 000 caractères."
        if errors:
            raise ValidationError(errors)

    def save(self, *args, **kwargs) -> None:
        if not self.reference:
            year = timezone.localdate().year
            while True:
                candidate = f"CARD-{year}-{secrets.token_hex(10).upper()}"
                if not type(self).objects.filter(reference=candidate).exists():
                    self.reference = candidate
                    break
        if self.intention == self.Intention.BUY:
            self.budget = f"{self.cardigan_quantity * 15_000:,}".replace(",", " ") + " F CFA"
        else:
            self.budget = ""
        super().save(*args, **kwargs)
