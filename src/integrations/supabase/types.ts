export type Json =
  | string
  | number
  | boolean
  | null
  | { [key: string]: Json | undefined }
  | Json[]

export type Database = {
  // Allows to automatically instantiate createClient with right options
  // instead of createClient<Database, { PostgrestVersion: 'XX' }>(URL, KEY)
  __InternalSupabase: {
    PostgrestVersion: "14.5"
  }
  public: {
    Tables: {
      audit_acceptance_memorandum: {
        Row: {
          accepted: boolean | null
          accepted_at: string | null
          auditor_name: string | null
          client_name: string
          commencement_date: string | null
          created_at: string
          engagement_number: string | null
          entity_id: string
          financial_year: string | null
          id: string
          notes: string | null
          risk_assessment: string | null
          updated_at: string
          user_id: string
        }
        Insert: {
          accepted?: boolean | null
          accepted_at?: string | null
          auditor_name?: string | null
          client_name: string
          commencement_date?: string | null
          created_at?: string
          engagement_number?: string | null
          entity_id: string
          financial_year?: string | null
          id?: string
          notes?: string | null
          risk_assessment?: string | null
          updated_at?: string
          user_id: string
        }
        Update: {
          accepted?: boolean | null
          accepted_at?: string | null
          auditor_name?: string | null
          client_name?: string
          commencement_date?: string | null
          created_at?: string
          engagement_number?: string | null
          entity_id?: string
          financial_year?: string | null
          id?: string
          notes?: string | null
          risk_assessment?: string | null
          updated_at?: string
          user_id?: string
        }
        Relationships: []
      }
      audit_fees: {
        Row: {
          agreed: boolean | null
          calculated_fee: number
          created_at: string
          digital_signature_date: string | null
          digital_signature_name: string | null
          entity_id: string
          id: string
          turnover: number
          updated_at: string
          user_id: string
        }
        Insert: {
          agreed?: boolean | null
          calculated_fee: number
          created_at?: string
          digital_signature_date?: string | null
          digital_signature_name?: string | null
          entity_id: string
          id?: string
          turnover: number
          updated_at?: string
          user_id: string
        }
        Update: {
          agreed?: boolean | null
          calculated_fee?: number
          created_at?: string
          digital_signature_date?: string | null
          digital_signature_name?: string | null
          entity_id?: string
          id?: string
          turnover?: number
          updated_at?: string
          user_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "audit_fees_entity_id_fkey"
            columns: ["entity_id"]
            isOneToOne: true
            referencedRelation: "entities"
            referencedColumns: ["id"]
          },
        ]
      }
      cdd_verifications: {
        Row: {
          admin_id: string
          auditor_verification: string | null
          created_at: string
          economic_sector: string | null
          eligibility_status: string | null
          eligibility_verification: string | null
          entity_id: string
          id: string
          identity_verification: string | null
          notes: string | null
          updated_at: string
          verification_history: Json | null
        }
        Insert: {
          admin_id: string
          auditor_verification?: string | null
          created_at?: string
          economic_sector?: string | null
          eligibility_status?: string | null
          eligibility_verification?: string | null
          entity_id: string
          id?: string
          identity_verification?: string | null
          notes?: string | null
          updated_at?: string
          verification_history?: Json | null
        }
        Update: {
          admin_id?: string
          auditor_verification?: string | null
          created_at?: string
          economic_sector?: string | null
          eligibility_status?: string | null
          eligibility_verification?: string | null
          entity_id?: string
          id?: string
          identity_verification?: string | null
          notes?: string | null
          updated_at?: string
          verification_history?: Json | null
        }
        Relationships: []
      }
      engagement_letters: {
        Row: {
          accepted: boolean | null
          accepted_at: string | null
          created_at: string
          engagement_number: string | null
          entity_id: string
          id: string
          letter_content: string | null
          updated_at: string
          user_id: string
        }
        Insert: {
          accepted?: boolean | null
          accepted_at?: string | null
          created_at?: string
          engagement_number?: string | null
          entity_id: string
          id?: string
          letter_content?: string | null
          updated_at?: string
          user_id: string
        }
        Update: {
          accepted?: boolean | null
          accepted_at?: string | null
          created_at?: string
          engagement_number?: string | null
          entity_id?: string
          id?: string
          letter_content?: string | null
          updated_at?: string
          user_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "engagement_letters_entity_id_fkey"
            columns: ["entity_id"]
            isOneToOne: false
            referencedRelation: "entities"
            referencedColumns: ["id"]
          },
        ]
      }
      entities: {
        Row: {
          address: string | null
          application_status: string
          application_type: string
          cdd_completed: boolean | null
          created_at: string
          current_step: number
          digital_signature_name: string | null
          digital_signature_requested_at: string | null
          digital_signature_required: boolean
          digital_signature_signed_at: string | null
          digital_signature_status: string
          emirate: string | null
          engagement_number: string | null
          entity_name: string
          financial_analyzed: boolean | null
          has_ubo: boolean | null
          id: string
          ind_completed: boolean | null
          license_expiry_date: string | null
          license_issue_date: string | null
          license_number: string | null
          main_activity: string | null
          mainland_company_type: string | null
          management_control: Json | null
          payment_status: string | null
          registration_status: string | null
          rejection_reason: string | null
          review_stage: string
          reviewed_at: string | null
          reviewed_by: string | null
          screening_completed: boolean | null
          shareholders: Json | null
          submitted_at: string | null
          total_turnover: number | null
          uae_id_verified: boolean | null
          ubos: Json | null
          updated_at: string
          user_id: string
        }
        Insert: {
          address?: string | null
          application_status?: string
          application_type?: string
          cdd_completed?: boolean | null
          created_at?: string
          current_step?: number
          digital_signature_name?: string | null
          digital_signature_requested_at?: string | null
          digital_signature_required?: boolean
          digital_signature_signed_at?: string | null
          digital_signature_status?: string
          emirate?: string | null
          engagement_number?: string | null
          entity_name: string
          financial_analyzed?: boolean | null
          has_ubo?: boolean | null
          id?: string
          ind_completed?: boolean | null
          license_expiry_date?: string | null
          license_issue_date?: string | null
          license_number?: string | null
          main_activity?: string | null
          mainland_company_type?: string | null
          management_control?: Json | null
          payment_status?: string | null
          registration_status?: string | null
          rejection_reason?: string | null
          review_stage?: string
          reviewed_at?: string | null
          reviewed_by?: string | null
          screening_completed?: boolean | null
          shareholders?: Json | null
          submitted_at?: string | null
          total_turnover?: number | null
          uae_id_verified?: boolean | null
          ubos?: Json | null
          updated_at?: string
          user_id: string
        }
        Update: {
          address?: string | null
          application_status?: string
          application_type?: string
          cdd_completed?: boolean | null
          created_at?: string
          current_step?: number
          digital_signature_name?: string | null
          digital_signature_requested_at?: string | null
          digital_signature_required?: boolean
          digital_signature_signed_at?: string | null
          digital_signature_status?: string
          emirate?: string | null
          engagement_number?: string | null
          entity_name?: string
          financial_analyzed?: boolean | null
          has_ubo?: boolean | null
          id?: string
          ind_completed?: boolean | null
          license_expiry_date?: string | null
          license_issue_date?: string | null
          license_number?: string | null
          main_activity?: string | null
          mainland_company_type?: string | null
          management_control?: Json | null
          payment_status?: string | null
          registration_status?: string | null
          rejection_reason?: string | null
          review_stage?: string
          reviewed_at?: string | null
          reviewed_by?: string | null
          screening_completed?: boolean | null
          shareholders?: Json | null
          submitted_at?: string | null
          total_turnover?: number | null
          uae_id_verified?: boolean | null
          ubos?: Json | null
          updated_at?: string
          user_id?: string
        }
        Relationships: []
      }
      financial_analyses: {
        Row: {
          ai_risks: Json | null
          ai_summary: string | null
          analyzed_at: string | null
          balance_sheet: Json | null
          created_at: string
          entity_id: string
          health_score: number | null
          id: string
          income_stmt: Json | null
          ratios: Json | null
          raw_data: Json | null
          source_files: Json | null
          user_id: string
        }
        Insert: {
          ai_risks?: Json | null
          ai_summary?: string | null
          analyzed_at?: string | null
          balance_sheet?: Json | null
          created_at?: string
          entity_id: string
          health_score?: number | null
          id?: string
          income_stmt?: Json | null
          ratios?: Json | null
          raw_data?: Json | null
          source_files?: Json | null
          user_id: string
        }
        Update: {
          ai_risks?: Json | null
          ai_summary?: string | null
          analyzed_at?: string | null
          balance_sheet?: Json | null
          created_at?: string
          entity_id?: string
          health_score?: number | null
          id?: string
          income_stmt?: Json | null
          ratios?: Json | null
          raw_data?: Json | null
          source_files?: Json | null
          user_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "financial_analyses_entity_id_fkey"
            columns: ["entity_id"]
            isOneToOne: false
            referencedRelation: "entities"
            referencedColumns: ["id"]
          },
        ]
      }
      financial_years: {
        Row: {
          created_at: string
          current_end_date: string | null
          current_start_date: string | null
          entity_id: string
          first_end_date: string | null
          first_start_date: string | null
          id: string
          is_first_year: boolean | null
          previous_audited: string | null
          previous_end_date: string | null
          previous_start_date: string | null
          updated_at: string
          user_id: string
        }
        Insert: {
          created_at?: string
          current_end_date?: string | null
          current_start_date?: string | null
          entity_id: string
          first_end_date?: string | null
          first_start_date?: string | null
          id?: string
          is_first_year?: boolean | null
          previous_audited?: string | null
          previous_end_date?: string | null
          previous_start_date?: string | null
          updated_at?: string
          user_id: string
        }
        Update: {
          created_at?: string
          current_end_date?: string | null
          current_start_date?: string | null
          entity_id?: string
          first_end_date?: string | null
          first_start_date?: string | null
          id?: string
          is_first_year?: boolean | null
          previous_audited?: string | null
          previous_end_date?: string | null
          previous_start_date?: string | null
          updated_at?: string
          user_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "financial_years_entity_id_fkey"
            columns: ["entity_id"]
            isOneToOne: true
            referencedRelation: "entities"
            referencedColumns: ["id"]
          },
        ]
      }
      independence_confirmations: {
        Row: {
          client_audit: string | null
          confirmation_status: string | null
          confirmation_text: string | null
          confirmation_type: string
          confirmed_by: string | null
          conflict_details: string | null
          created_at: string
          engagement_number: string | null
          entity_id: string
          id: string
          is_sent: boolean | null
          sent_at: string | null
          signature_date: string | null
          signature_name: string | null
          status_message: string | null
          updated_at: string
          user_id: string
        }
        Insert: {
          client_audit?: string | null
          confirmation_status?: string | null
          confirmation_text?: string | null
          confirmation_type: string
          confirmed_by?: string | null
          conflict_details?: string | null
          created_at?: string
          engagement_number?: string | null
          entity_id: string
          id?: string
          is_sent?: boolean | null
          sent_at?: string | null
          signature_date?: string | null
          signature_name?: string | null
          status_message?: string | null
          updated_at?: string
          user_id: string
        }
        Update: {
          client_audit?: string | null
          confirmation_status?: string | null
          confirmation_text?: string | null
          confirmation_type?: string
          confirmed_by?: string | null
          conflict_details?: string | null
          created_at?: string
          engagement_number?: string | null
          entity_id?: string
          id?: string
          is_sent?: boolean | null
          sent_at?: string | null
          signature_date?: string | null
          signature_name?: string | null
          status_message?: string | null
          updated_at?: string
          user_id?: string
        }
        Relationships: []
      }
      kyc_documents: {
        Row: {
          document_type: string
          entity_id: string
          file_name: string
          id: string
          mime_type: string | null
          rejection_reason: string | null
          reviewed_at: string | null
          reviewed_by: string | null
          size_bytes: number | null
          status: string
          storage_path: string
          uploaded_at: string
          user_id: string
        }
        Insert: {
          document_type: string
          entity_id: string
          file_name: string
          id?: string
          mime_type?: string | null
          rejection_reason?: string | null
          reviewed_at?: string | null
          reviewed_by?: string | null
          size_bytes?: number | null
          status?: string
          storage_path: string
          uploaded_at?: string
          user_id: string
        }
        Update: {
          document_type?: string
          entity_id?: string
          file_name?: string
          id?: string
          mime_type?: string | null
          rejection_reason?: string | null
          reviewed_at?: string | null
          reviewed_by?: string | null
          size_bytes?: number | null
          status?: string
          storage_path?: string
          uploaded_at?: string
          user_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "kyc_documents_entity_id_fkey"
            columns: ["entity_id"]
            isOneToOne: false
            referencedRelation: "entities"
            referencedColumns: ["id"]
          },
        ]
      }
      payments: {
        Row: {
          amount: number
          created_at: string
          currency: string
          entity_id: string
          gateway_ref: string | null
          id: string
          method: string | null
          paid_at: string | null
          reference: string | null
          status: string
          user_id: string
        }
        Insert: {
          amount: number
          created_at?: string
          currency?: string
          entity_id: string
          gateway_ref?: string | null
          id?: string
          method?: string | null
          paid_at?: string | null
          reference?: string | null
          status?: string
          user_id: string
        }
        Update: {
          amount?: number
          created_at?: string
          currency?: string
          entity_id?: string
          gateway_ref?: string | null
          id?: string
          method?: string | null
          paid_at?: string | null
          reference?: string | null
          status?: string
          user_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "payments_entity_id_fkey"
            columns: ["entity_id"]
            isOneToOne: false
            referencedRelation: "entities"
            referencedColumns: ["id"]
          },
        ]
      }
      profiles: {
        Row: {
          created_at: string
          email: string | null
          full_name: string | null
          id: string
          phone: string | null
          role: string | null
          updated_at: string
        }
        Insert: {
          created_at?: string
          email?: string | null
          full_name?: string | null
          id: string
          phone?: string | null
          role?: string | null
          updated_at?: string
        }
        Update: {
          created_at?: string
          email?: string | null
          full_name?: string | null
          id?: string
          phone?: string | null
          role?: string | null
          updated_at?: string
        }
        Relationships: []
      }
      sanctions_list: {
        Row: {
          arabic_name: string | null
          country: string | null
          created_at: string
          english_name: string
          expiry_date: string | null
          id: string
          list_reference: string | null
          source: string | null
          status: string
          type: string | null
          updated_at: string
        }
        Insert: {
          arabic_name?: string | null
          country?: string | null
          created_at?: string
          english_name: string
          expiry_date?: string | null
          id?: string
          list_reference?: string | null
          source?: string | null
          status?: string
          type?: string | null
          updated_at?: string
        }
        Update: {
          arabic_name?: string | null
          country?: string | null
          created_at?: string
          english_name?: string
          expiry_date?: string | null
          id?: string
          list_reference?: string | null
          source?: string | null
          status?: string
          type?: string | null
          updated_at?: string
        }
        Relationships: []
      }
      screening_results: {
        Row: {
          admin_result: string | null
          ai_result: string
          created_at: string
          entity_id: string
          id: string
          name_to_screen: string
          name_type: string | null
          notes: string | null
          screened_at: string
          user_id: string
          verified_at: string | null
          verified_by: string | null
        }
        Insert: {
          admin_result?: string | null
          ai_result?: string
          created_at?: string
          entity_id: string
          id?: string
          name_to_screen: string
          name_type?: string | null
          notes?: string | null
          screened_at?: string
          user_id: string
          verified_at?: string | null
          verified_by?: string | null
        }
        Update: {
          admin_result?: string | null
          ai_result?: string
          created_at?: string
          entity_id?: string
          id?: string
          name_to_screen?: string
          name_type?: string | null
          notes?: string | null
          screened_at?: string
          user_id?: string
          verified_at?: string | null
          verified_by?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "screening_results_entity_id_fkey"
            columns: ["entity_id"]
            isOneToOne: false
            referencedRelation: "entities"
            referencedColumns: ["id"]
          },
        ]
      }
      tax_status: {
        Row: {
          corporate_tax_registration_number: string | null
          corporate_tax_status: string | null
          corporate_tax_treatment: string | null
          created_at: string
          entity_id: string
          excise_tax_status: string | null
          id: string
          not_registered_reason: string | null
          other_reason_details: string | null
          previous_data: Json | null
          small_business_relief: string | null
          updated_at: string
          user_id: string
          vat_registration_number: string | null
          vat_status: string | null
        }
        Insert: {
          corporate_tax_registration_number?: string | null
          corporate_tax_status?: string | null
          corporate_tax_treatment?: string | null
          created_at?: string
          entity_id: string
          excise_tax_status?: string | null
          id?: string
          not_registered_reason?: string | null
          other_reason_details?: string | null
          previous_data?: Json | null
          small_business_relief?: string | null
          updated_at?: string
          user_id: string
          vat_registration_number?: string | null
          vat_status?: string | null
        }
        Update: {
          corporate_tax_registration_number?: string | null
          corporate_tax_status?: string | null
          corporate_tax_treatment?: string | null
          created_at?: string
          entity_id?: string
          excise_tax_status?: string | null
          id?: string
          not_registered_reason?: string | null
          other_reason_details?: string | null
          previous_data?: Json | null
          small_business_relief?: string | null
          updated_at?: string
          user_id?: string
          vat_registration_number?: string | null
          vat_status?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "tax_status_entity_id_fkey"
            columns: ["entity_id"]
            isOneToOne: true
            referencedRelation: "entities"
            referencedColumns: ["id"]
          },
        ]
      }
      uae_id_verifications: {
        Row: {
          back_path: string | null
          created_at: string
          dob: string | null
          entity_id: string
          expiry_date: string | null
          front_path: string | null
          full_name_ar: string | null
          full_name_en: string | null
          gender: string | null
          id: string
          id_number: string
          nationality: string | null
          ocr_data: Json | null
          status: string
          user_id: string
          verified_at: string | null
        }
        Insert: {
          back_path?: string | null
          created_at?: string
          dob?: string | null
          entity_id: string
          expiry_date?: string | null
          front_path?: string | null
          full_name_ar?: string | null
          full_name_en?: string | null
          gender?: string | null
          id?: string
          id_number: string
          nationality?: string | null
          ocr_data?: Json | null
          status?: string
          user_id: string
          verified_at?: string | null
        }
        Update: {
          back_path?: string | null
          created_at?: string
          dob?: string | null
          entity_id?: string
          expiry_date?: string | null
          front_path?: string | null
          full_name_ar?: string | null
          full_name_en?: string | null
          gender?: string | null
          id?: string
          id_number?: string
          nationality?: string | null
          ocr_data?: Json | null
          status?: string
          user_id?: string
          verified_at?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "uae_id_verifications_entity_id_fkey"
            columns: ["entity_id"]
            isOneToOne: false
            referencedRelation: "entities"
            referencedColumns: ["id"]
          },
        ]
      }
      user_audit_logs: {
        Row: {
          action: string
          created_at: string
          description: string | null
          id: string
          ip_address: string | null
          metadata: Json | null
          user_agent: string | null
          user_id: string
        }
        Insert: {
          action: string
          created_at?: string
          description?: string | null
          id?: string
          ip_address?: string | null
          metadata?: Json | null
          user_agent?: string | null
          user_id: string
        }
        Update: {
          action?: string
          created_at?: string
          description?: string | null
          id?: string
          ip_address?: string | null
          metadata?: Json | null
          user_agent?: string | null
          user_id?: string
        }
        Relationships: []
      }
      user_roles: {
        Row: {
          created_at: string
          id: string
          role: Database["public"]["Enums"]["app_role"]
          user_id: string
        }
        Insert: {
          created_at?: string
          id?: string
          role?: Database["public"]["Enums"]["app_role"]
          user_id: string
        }
        Update: {
          created_at?: string
          id?: string
          role?: Database["public"]["Enums"]["app_role"]
          user_id?: string
        }
        Relationships: []
      }
    }
    Views: {
      [_ in never]: never
    }
    Functions: {
      can_manage_staff: { Args: { _user_id: string }; Returns: boolean }
      get_entity_stats: {
        Args: never
        Returns: {
          count: number
          status: string
        }[]
      }
      has_role: {
        Args: {
          _role: Database["public"]["Enums"]["app_role"]
          _user_id: string
        }
        Returns: boolean
      }
    }
    Enums: {
      app_role: "admin" | "moderator" | "user" | "auditor" | "manager"
    }
    CompositeTypes: {
      [_ in never]: never
    }
  }
}

type DatabaseWithoutInternals = Omit<Database, "__InternalSupabase">

type DefaultSchema = DatabaseWithoutInternals[Extract<keyof Database, "public">]

export type Tables<
  DefaultSchemaTableNameOrOptions extends
    | keyof (DefaultSchema["Tables"] & DefaultSchema["Views"])
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof (DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
        DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Views"])
    : never = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? (DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
      DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Views"])[TableName] extends {
      Row: infer R
    }
    ? R
    : never
  : DefaultSchemaTableNameOrOptions extends keyof (DefaultSchema["Tables"] &
        DefaultSchema["Views"])
    ? (DefaultSchema["Tables"] &
        DefaultSchema["Views"])[DefaultSchemaTableNameOrOptions] extends {
        Row: infer R
      }
      ? R
      : never
    : never

export type TablesInsert<
  DefaultSchemaTableNameOrOptions extends
    | keyof DefaultSchema["Tables"]
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
    : never = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
      Insert: infer I
    }
    ? I
    : never
  : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
    ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
        Insert: infer I
      }
      ? I
      : never
    : never

export type TablesUpdate<
  DefaultSchemaTableNameOrOptions extends
    | keyof DefaultSchema["Tables"]
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
    : never = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
      Update: infer U
    }
    ? U
    : never
  : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
    ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
        Update: infer U
      }
      ? U
      : never
    : never

export type Enums<
  DefaultSchemaEnumNameOrOptions extends
    | keyof DefaultSchema["Enums"]
    | { schema: keyof DatabaseWithoutInternals },
  EnumName extends DefaultSchemaEnumNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"]
    : never = never,
> = DefaultSchemaEnumNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"][EnumName]
  : DefaultSchemaEnumNameOrOptions extends keyof DefaultSchema["Enums"]
    ? DefaultSchema["Enums"][DefaultSchemaEnumNameOrOptions]
    : never

export type CompositeTypes<
  PublicCompositeTypeNameOrOptions extends
    | keyof DefaultSchema["CompositeTypes"]
    | { schema: keyof DatabaseWithoutInternals },
  CompositeTypeName extends PublicCompositeTypeNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"]
    : never = never,
> = PublicCompositeTypeNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"][CompositeTypeName]
  : PublicCompositeTypeNameOrOptions extends keyof DefaultSchema["CompositeTypes"]
    ? DefaultSchema["CompositeTypes"][PublicCompositeTypeNameOrOptions]
    : never

export const Constants = {
  public: {
    Enums: {
      app_role: ["admin", "moderator", "user", "auditor", "manager"],
    },
  },
} as const
