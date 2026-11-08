from django.contrib.auth import get_user_model
from django.contrib.auth.backends import ModelBackend


class EmailOrUsernameModelBackend(ModelBackend):
    def authenticate(self, request, username=None, password=None, **kwargs):
        UserModel = get_user_model()
        username = username or kwargs.get(UserModel.USERNAME_FIELD)
        if username and "@" in username:
            matches = UserModel._default_manager.filter(email__iexact=username)
            if matches.count() != 1:
                return None
            username = matches.get().get_username()
        return super().authenticate(request, username=username, password=password, **kwargs)
