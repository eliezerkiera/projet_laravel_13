je veux que tu developpe un systeme d'authentification des utilisateurs complet en utilisant:
- API REST
- Laravel 13 et sanctum

cette API sera consommee plutard par une single page application et une application mobile

# Instructions

## Register

- l'utilisateur saisi d'abord son email
- un code de verification est envoye en utilisant l'email saisi avec un delai de 10 minutes
- l'utilisateur saisi le code de verification
- si le code est valide, l'utilisateur peut saisir d'autres informations (first_name, last_name, password, country, language)
- il y a la possibilite de renvoyer le code

## login

- l'utilisateur saisi son email et mot de passe
- un code de verification est envoyé
- l'utilisateur saisi le code
- si le code est valide on cree un access token

## forgot password

- l'utilisateur saisi son email
- un code est envoye a l'adresse email
- l'utilisateur saisi le code
- si le code est valide l'utilisateur saisi le nouveau mot de passe

## Update profil

- l'utilisateur peut modifier les champs (first_name, last_name, country_id, language_id, email)
- si l'email est changé un code de verification est envoye a la nouvelle addresse et l'utilisateur

## change password

- l'utilisateur saisi son mot de passe actuel et le nouveau mot de passe

## logout

- l'utilisateur se deconnecte

## delete

- l'utilisateur doit saisir le mot de passe avant de pouvoir supprimer
- la suppression sera en soft delete

## gestion des appareils connectes

- liste les appareils connectes
- possibilite de deconnecter un appareil
- possibilite de deconnecter les autres appareils sauf l'appareil courant

## langues

- les messages a afficher seront traduits en plusieurs langues (anglais et francais)

## autres

- l'API sera en version 2
