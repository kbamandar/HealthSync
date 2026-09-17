terraform {
  required_version = ">= 1.7.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }

  # Remote state backend — bootstrap the S3 bucket + DynamoDB lock table
  # out-of-band first, then uncomment. Left disabled so a bare `terraform
  # init` doesn't fail before that bucket exists.
  # backend "s3" {
  #   bucket         = "healthsync-terraform-state"
  #   key            = "staging/terraform.tfstate"
  #   region         = "ap-south-1"
  #   dynamodb_table = "healthsync-terraform-locks"
  #   encrypt        = true
  # }
}

provider "aws" {
  region = var.aws_region
}
