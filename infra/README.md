# Infrastructure (Terraform)

Structure only — provisioning real AWS resources (`terraform init`/`plan`/`apply`)
is handled outside this scaffold.

## Layout

```
infra/
├── modules/
│   ├── vpc/       VPC, public/private subnets, NAT gateway
│   ├── rds/       PostgreSQL, private-subnet only, encrypted at rest
│   ├── redis/     ElastiCache, backs Horizon queue + cache driver
│   ├── ecs/       Fargate cluster, ALB, task definition, service
│   ├── ecr/       Container image repositories
│   └── secrets/   Secrets Manager containers (values set out-of-band)
└── environments/
    ├── staging/       1 ECS task, db.t3.small, single-AZ
    └── production/    2 ECS tasks, db.t3.medium, multi-AZ
```

## Before running `terraform apply`

1. Bootstrap the remote state backend (S3 bucket + DynamoDB lock table) manually,
   then uncomment the `backend "s3"` block in each environment's `versions.tf`.
2. Copy `terraform.tfvars.example` to `terraform.tfvars` in the environment
   directory and fill in real values (`container_image`, `acm_certificate_arn`).
   Never commit `terraform.tfvars` — it's gitignored.
3. Pass `db_master_password` via `TF_VAR_db_master_password` from a secret
   store, not typed into a file.
4. `cd infra/environments/staging && terraform init && terraform plan`.

## Deliberately left out of this scaffold

CloudFront, AWS WAF, and GuardDuty (§11 launch checklist) need a real domain
and traffic patterns to configure sensibly — added when production is closer
to launch, not guessed at here.
