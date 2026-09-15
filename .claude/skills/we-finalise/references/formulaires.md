# Formulaires : destinataire et expéditeur

Un formulaire cassé ne se voit pas. Le visiteur envoie, la page affiche « merci », et personne ne
reçoit rien. C'est la seule panne de livraison qui coûte des clients au client sans qu'il le
sache — souvent pendant des mois. D'où deux contrôles non négociables avant mise en ligne :
**il y a un destinataire**, et **l'expéditeur est au bon domaine**.

## La règle

| Champ | Valeur | Pourquoi |
|---|---|---|
| Destinataire (`To`) | l'adresse du client, en dur | Ni l'agence, ni `admin_email` laissé par défaut |
| Expéditeur (`From`) | `formulaire@<domaine de prod>` | Doit être au domaine qui signe le mail (SPF/DKIM) |
| `Reply-To` | l'email saisi par le visiteur | C'est ce qui permet de répondre d'un clic |
| Objet | explicite, avec le nom du site | Repérable dans une boîte chargée |

Le point qui fait tomber la majorité des sites : **le `From` ne doit jamais porter l'adresse du
visiteur.** Un mail émis par le serveur du client avec `From: visiteur@gmail.com` est rejeté par la
politique DMARC de Gmail — le message n'arrive pas, ou tombe en spam. L'adresse du visiteur va dans
`Reply-To`, jamais dans `From`. Un `From` resté sur `wordpress@staging.client.fr` ou
`wordpress@<hébergeur>` échoue de la même façon : le domaine expéditeur ne correspond à rien de
signé.

## Dériver le domaine

`formulaire@<domaine>` où `<domaine>` est le **domaine de production**, pas celui du staging.
`scripts/php/forms-audit.php` le dérive du `prod_url` de `.we-finalise/context.json`, qu'on lui
passe dans `$args[0]` (`mcp.md` §3) : protocole, port, chemin et `www.` retirés, sous-domaine réduit
au domaine enregistré, suffixes composés (`co.uk`, `asso.fr`…) préservés.

Le piège : un staging sur le domaine de l'hébergeur ou de l'agence
(`client.werocket.ovh`, `client.o2switch.net`) produirait `formulaire@werocket.ovh` — une adresse
qui n'est pas celle du client. La charge avertit quand elle a réduit un sous-domaine ; si `prod_url`
n'est pas établi, n'écris aucune adresse expéditeur et mets la question au rapport plutôt que
d'accepter le domaine dérivé.

## Où c'est stocké

- **FormBuilder Breakdance** : dans l'arbre JSON `breakdance_data`, sous les actions du formulaire.
  Les noms de propriétés varient selon la version : `scripts/php/forms-audit.php` remonte le
  **chemin JSON** de chaque valeur trouvée, et `scripts/php/apply-forms.php` écrit à ce chemin exact
  (passe d'essai avec `"dry_run": true`). Un chemin inexistant est refusé plutôt que créé : une
  propriété que le builder ne lit pas ne règle rien. L'outil natif `breakdance-set-element-form`
  reste le chemin par défaut quand il s'agit de régler un formulaire entier.
- **Contact Form 7** : postmeta `_mail` du CPT `wpcf7_contact_form` (`recipient`, `sender`,
  `subject`). CF7 écrit l'expéditeur au format `Nom <adresse>` — c'est correct, ne le « corrige »
  pas.
- **WPForms** : JSON dans le `post_content` du CPT `wpforms`, clé
  `settings.notifications[].sender_address`.
- **Gravity Forms, Fluent Forms, Forminator, Ninja Forms** : tables propres au plugin.
  `forms-audit.php` les signale comme présents mais ne lit pas leur configuration : à vérifier dans
  leur interface, et à noter dans le rapport.
- **Plugin SMTP** (WP Mail SMTP, FluentSMTP, Post SMTP…) : expéditeur global du site.

## L'ordre de correction

1. **D'abord le plugin SMTP.** Régler `from_email` sur `formulaire@<domaine>` et activer « Force
   From Email » couvre tout le site d'un coup — formulaires, notifications WordPress,
   réinitialisations de mot de passe — et rend les réglages par formulaire moins critiques. Sans
   plugin SMTP, les mails partent par `mail()` du serveur : délivrabilité faible, à signaler comme
   bloquant dans le rapport.
2. **Ensuite chaque formulaire**, pour que rien ne contredise le réglage global.
3. **Puis le test réel** (voir plus bas).

## Le test qui compte

La configuration en base ne prouve pas qu'un mail arrive. Avant de cocher cette étape :

1. Envoyer un message depuis le formulaire **sur le front**, avec une adresse réelle dans le champ
   email du visiteur.
2. Vérifier la réception dans la boîte du destinataire (demander confirmation au client si sa boîte
   n'est pas accessible — et le noter comme « à confirmer par le client » dans le rapport, jamais
   comme vérifié).
3. Vérifier que **répondre** au message part bien vers l'adresse du visiteur (`Reply-To`).
4. Regarder l'en-tête `From` du message reçu : c'est là qu'on voit si le plugin SMTP a bien forcé
   l'expéditeur.

Si le site est encore en staging, un envoi peut être bloqué ou réécrit : le noter et refaire le
test après mise en ligne. C'est une des vérifications de la section « à la mise en ligne » du
rapport.

## Délivrabilité

`formulaire@<domaine>` doit **exister** chez le client (boîte ou alias) : une adresse expéditeur
inexistante fait échouer les retours d'erreur et dégrade la réputation du domaine. Si le client ne
peut pas la créer, l'alternative est `contact@<domaine>` — jamais une adresse hors domaine.

À la mise en ligne, SPF et DKIM doivent couvrir le serveur qui envoie. Cela vit hors du site, donc
hors de portée du canal MCP (`mcp.md` §8) : c'est une demande à l'hébergeur ou au registrar, à
inscrire dans le rapport.

## Critères d'acceptation

L'étape est finie quand, pour **chaque** formulaire du site :

- [ ] au moins un destinataire non vide, qui est bien une adresse du client ;
- [ ] aucun destinataire construit sur un champ du visiteur (`[votre-email]` dans le `To` envoie le
      message au visiteur) ;
- [ ] `From` = `formulaire@<domaine de prod>` (ou l'adresse retenue avec le client) ;
- [ ] `Reply-To` = email du visiteur ;
- [ ] un test d'envoi réel reçu, ou explicitement noté « à confirmer par le client » ;
- [ ] un plugin SMTP actif et configuré, ou le manque signalé comme bloquant.
