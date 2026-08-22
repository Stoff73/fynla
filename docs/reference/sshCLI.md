# SSH CLI Commands - fynla.org

## Connect to Server

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
```

## Navigate to Application

```bash
cd ~/www/fynla.org/public_html
```

## Cache Clearing & Optimisation

```bash
php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan optimize
```

## Preview User Management

### Delete all preview users

```bash
php artisan tinker --execute="App\Models\User::where('is_preview_user', true)->delete();"
```

```bash
php artisan tinker --execute="                                                                                            
  \App\Models\User::where('is_preview_user', true)->each(function(\$u) {                                                    
      \App\Models\Property::where('user_id', \$u->id)->orWhere('joint_owner_id', \$u->id)->delete();                        
      \App\Models\Mortgage::where('user_id', \$u->id)->orWhere('joint_owner_id', \$u->id)->delete();                        
      \$u->delete();                                                                                                        
  });                                                                                                                       
  echo 'All preview users deleted';                                                                                         
  "                                     
```

### Reseed preview users

```bash
php artisan db:seed --class=PreviewUserSeeder --force
```

### Full preview user reset (delete + reseed)

```bash
php artisan tinker --execute="App\Models\User::where('is_preview_user', true)->delete();" && php artisan db:seed --class=PreviewUserSeeder --force
```

## Common Post-Deployment Sequence

```bash
cd ~/www/fynla.org/public_html
php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan optimize
php artisan tinker --execute="App\Models\User::where('is_preview_user', true)->delete();"
php artisan db:seed --class=PreviewUserSeeder --force
```

## Migrate

```bash
php artisan migrate --force
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear
```

## Run all seeders

```bash
cd ~/www/fynla.orgpublic_html                                                                             
  php artisan db:seed --force      
                                                                        
  php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
  ```

  ```bash
  php artisan db:seed --class=OccupationCodeSeeder --force
  php artisan db:seed --class=PreviewUserSeeder --force
  php artisan db:seed --class=ActuarialLifeTableSeeder --force
  php artisan db:seed --class=TaxConfigurationSeeder --force
  php artisan db:seed --class=TaxProductReferenceSeeder --force
  ```