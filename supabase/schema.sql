create table if not exists public.event_registrations (
  id bigint generated always as identity primary key,
  reference text not null unique default ('INV-' || upper(replace(gen_random_uuid()::text, '-', ''))),
  first_name text not null,
  last_name text not null,
  age integer,
  city text not null,
  attendance text not null check (attendance in ('present', 'absent', 'unsure')),
  companions_count integer not null default 0 check (companions_count between 0 and 20),
  beverages text not null check (beverages in ('alcohol', 'non_alcoholic', 'both', 'none')),
  cardigan_knowledge text not null check (cardigan_knowledge in ('known', 'heard', 'new')),
  intention text not null check (intention in ('buy', 'discover', 'inform', 'accompany')),
  desired_size text not null,
  cardigan_quantity integer not null default 0 check (cardigan_quantity between 0 and 20),
  preferred_color text,
  comment text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint purchase_quantity_consistent check (
    (intention = 'buy' and cardigan_quantity between 1 and 20)
    or (intention <> 'buy' and cardigan_quantity = 0)
  ),
  constraint companions_consistent check (
    (attendance = 'present' or companions_count = 0)
  )
);

alter table public.event_registrations enable row level security;

drop policy if exists "Authenticated admins manage registrations" on public.event_registrations;
create policy "Authenticated admins manage registrations"
  on public.event_registrations
  for all
  to authenticated
  using ((auth.jwt() -> 'app_metadata' ->> 'role') = 'admin')
  with check ((auth.jwt() -> 'app_metadata' ->> 'role') = 'admin');

revoke all on public.event_registrations from anon, authenticated;
revoke all on public.event_registrations from public;
grant select, update, delete on public.event_registrations to authenticated;

create or replace function public.register_cardigan(p_data jsonb)
returns jsonb
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_attendance text := p_data ->> 'attendance';
  v_group_type text := p_data ->> 'groupType';
  v_intention text := p_data ->> 'intention';
  v_companions integer := coalesce(nullif(p_data ->> 'companionsCount', '')::integer, 0);
  v_quantity integer := coalesce(nullif(p_data ->> 'cardiganQuantity', '')::integer, 0);
  v_age integer := nullif(p_data ->> 'age', '')::integer;
  v_registration public.event_registrations;
begin
  if length(trim(coalesce(p_data ->> 'firstName', ''))) not between 1 and 120
    or length(trim(coalesce(p_data ->> 'lastName', ''))) not between 1 and 120
    or length(trim(coalesce(p_data ->> 'city', ''))) not between 1 and 120
    or length(trim(coalesce(p_data ->> 'desiredSize', ''))) not between 1 and 30 then
    raise exception 'Veuillez renseigner vos informations obligatoires.';
  end if;
  if v_age is not null and v_age not between 0 and 120 then
    raise exception 'L’âge doit être compris entre 0 et 120.';
  end if;
  if v_attendance not in ('present', 'absent', 'unsure')
    or v_group_type not in ('alone', 'accompanied')
    or p_data ->> 'beverages' not in ('alcohol', 'non_alcoholic', 'both', 'none')
    or p_data ->> 'cardiganKnowledge' not in ('known', 'heard', 'new')
    or v_intention not in ('buy', 'discover', 'inform', 'accompany') then
    raise exception 'Une des réponses du formulaire est invalide.';
  end if;
  if v_attendance = 'present' and v_group_type = 'accompanied' then
    if v_companions not between 1 and 20 then
      raise exception 'Le nombre d’accompagnants doit être compris entre 1 et 20.';
    end if;
  else
    v_companions := 0;
  end if;
  if v_intention = 'buy' and v_quantity not between 1 and 20 then
    raise exception 'La quantité doit être comprise entre 1 et 20 cardigans.';
  elsif v_intention <> 'buy' then
    v_quantity := 0;
  end if;
  if length(coalesce(p_data ->> 'preferredColor', '')) > 120
    or length(coalesce(p_data ->> 'comment', '')) > 1000 then
    raise exception 'Un champ dépasse la longueur maximale autorisée.';
  end if;

  insert into public.event_registrations (
    first_name, last_name, age, city, attendance, companions_count, beverages,
    cardigan_knowledge, intention, desired_size, cardigan_quantity, preferred_color, comment
  ) values (
    trim(p_data ->> 'firstName'), trim(p_data ->> 'lastName'), v_age,
    trim(p_data ->> 'city'), v_attendance, v_companions, p_data ->> 'beverages',
    p_data ->> 'cardiganKnowledge', v_intention, trim(p_data ->> 'desiredSize'),
    v_quantity, nullif(trim(p_data ->> 'preferredColor'), ''),
    nullif(trim(p_data ->> 'comment'), '')
  )
  returning * into v_registration;

  return jsonb_build_object(
    'reference', v_registration.reference,
    'first_name', v_registration.first_name,
    'last_name', v_registration.last_name,
    'city', v_registration.city,
    'attendance', v_registration.attendance,
    'companions_count', v_registration.companions_count,
    'beverages', v_registration.beverages,
    'cardigan_knowledge', v_registration.cardigan_knowledge,
    'intention', v_registration.intention,
    'desired_size', v_registration.desired_size,
    'cardigan_quantity', v_registration.cardigan_quantity,
    'created_at', v_registration.created_at,
    'updated_at', v_registration.updated_at
  );
end;
$$;

create or replace function public.get_cardigan_registration(
  p_reference text,
  p_last_name text default null
)
returns jsonb
language sql
security definer
set search_path = public, pg_temp
as $$
  select jsonb_build_object(
    'reference', r.reference,
    'first_name', r.first_name,
    'last_name', r.last_name,
    'city', r.city,
    'attendance', r.attendance,
    'companions_count', r.companions_count,
    'beverages', r.beverages,
    'cardigan_knowledge', r.cardigan_knowledge,
    'intention', r.intention,
    'desired_size', r.desired_size,
    'cardigan_quantity', r.cardigan_quantity,
    'created_at', r.created_at,
    'updated_at', r.updated_at
  )
  from public.event_registrations r
  where lower(r.reference) = lower(trim(p_reference))
    and (p_last_name is null or lower(trim(r.last_name)) = lower(trim(p_last_name)))
  limit 1;
$$;

create or replace function public.update_cardigan_rsvp(
  p_reference text,
  p_last_name text,
  p_attendance text,
  p_group_type text,
  p_companions_count integer
)
returns jsonb
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_companions integer := coalesce(p_companions_count, 0);
  v_registration public.event_registrations;
begin
  if p_attendance not in ('present', 'absent', 'unsure')
    or p_group_type not in ('alone', 'accompanied') then
    raise exception 'Votre réponse est invalide.';
  end if;
  if p_attendance = 'present' and p_group_type = 'accompanied' then
    if v_companions not between 1 and 20 then
      raise exception 'Le nombre d’accompagnants doit être compris entre 1 et 20.';
    end if;
  else
    v_companions := 0;
  end if;

  update public.event_registrations
  set attendance = p_attendance, companions_count = v_companions, updated_at = now()
  where lower(reference) = lower(trim(p_reference))
    and lower(trim(last_name)) = lower(trim(p_last_name))
  returning * into v_registration;

  if not found then
    return null;
  end if;
  return jsonb_build_object(
    'reference', v_registration.reference,
    'first_name', v_registration.first_name,
    'last_name', v_registration.last_name,
    'city', v_registration.city,
    'attendance', v_registration.attendance,
    'companions_count', v_registration.companions_count,
    'beverages', v_registration.beverages,
    'cardigan_knowledge', v_registration.cardigan_knowledge,
    'intention', v_registration.intention,
    'desired_size', v_registration.desired_size,
    'cardigan_quantity', v_registration.cardigan_quantity,
    'created_at', v_registration.created_at,
    'updated_at', v_registration.updated_at
  );
end;
$$;

create or replace function public.is_cardigan_admin()
returns boolean
language sql
stable
as $$
  select coalesce(auth.jwt() -> 'app_metadata' ->> 'role' = 'admin', false);
$$;

revoke all on function public.register_cardigan(jsonb) from public;
revoke all on function public.get_cardigan_registration(text, text) from public;
revoke all on function public.update_cardigan_rsvp(text, text, text, text, integer) from public;
revoke all on function public.is_cardigan_admin() from public;
grant execute on function public.register_cardigan(jsonb) to anon, authenticated;
grant execute on function public.get_cardigan_registration(text, text) to anon, authenticated;
grant execute on function public.update_cardigan_rsvp(text, text, text, text, integer) to anon, authenticated;
grant execute on function public.is_cardigan_admin() to authenticated;
